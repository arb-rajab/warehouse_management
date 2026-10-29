<?php

use App\Http\Middleware\AppLanguage;
use App\Http\Middleware\IsDeliveryBoy;
use App\Http\Middleware\IsDriver;
use App\Http\Middleware\SetLocaleFromHeader;
use App\Http\Middleware\WmsAuthMiddleware;
use App\Models\PersonalAccessToken;
use App\Support\StoreLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function apiLocaleSnapshot(): JsonResponse
{
    return response()->json([
        'content' => app()->getLocale(),
        'ui' => app('translator')->getLocale(),
        'fallback' => app('translator')->getFallback(),
        'default_content' => default_language(),
        'label' => __('storefront.auth.login'),
    ]);
}

test('store headers select UI and content separately and restore the surrounding locale', function (?string $header, string $content, string $ui) {
    config(['app.ui_default_locale' => 'en', 'app.default_language' => 'sa', 'app.fallback_locale' => 'sa']);
    app()->setLocale('sa');
    app('translator')->setLocale('ar');
    app('translator')->setFallback('en');
    $request = Request::create('/temp/api/v2/auth/info');
    $request->headers->set('Accept-Language', 'ar');
    if ($header !== null) {
        $request->headers->set('App-Language', $header);
    }

    startStorefrontTranslationQueryLog();
    $response = (new SetLocaleFromHeader)->handle($request, fn (Request $request) => (new AppLanguage)->handle($request, fn () => apiLocaleSnapshot()));

    expect($response->getData(true))->toBe([
        'content' => $content, 'ui' => $ui, 'fallback' => 'ar', 'default_content' => 'sa',
        'label' => $ui === 'ar' ? 'تسجيل الدخول' : 'Login',
    ]);
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe('sa')
        ->and(app('translator')->getLocale())->toBe('ar')
        ->and(app('translator')->getFallback())->toBe('en')
        ->and(config('app.ui_default_locale'))->toBe('en');
})->with([
    'English overrides Accept-Language' => ['en', 'en', 'en'],
    'Arabic' => ['ar', 'ar', 'ar'],
    'legacy Arabic' => ['sa', 'sa', 'ar'],
    'absent uses UI default and content default' => [null, 'sa', 'en'],
    'empty' => ['', 'sa', 'en'],
    'unsupported' => ['fr', 'sa', 'en'],
    'wrong case retains strict App-Language contract' => ['AR', 'sa', 'en'],
    'weighted list is not an App-Language code' => ['ar,en;q=0.8', 'sa', 'en'],
    'path traversal' => ['../../ar', 'sa', 'en'],
]);

test('Accept-Language negotiates valid weighted regional choices with an immutable default', function (?string $header, string $expected) {
    config(['app.ui_default_locale' => 'ar']);
    app()->setLocale('en');
    app('translator')->setFallback('en');
    $request = Request::create('/api/v1/products');
    $request->headers->remove('Accept-Language');
    if ($header !== null) {
        $request->headers->set('Accept-Language', $header);
    }

    $response = (new SetLocaleFromHeader)->handle($request, fn () => apiLocaleSnapshot());

    expect($response->getData(true)['ui'])->toBe($expected)
        ->and(app()->getLocale())->toBe('en')
        ->and(app('translator')->getLocale())->toBe('en');
})->with([
    'English' => ['en', 'en'],
    'Arabic' => ['ar', 'ar'],
    'legacy alias' => ['sa', 'ar'],
    'weighted region' => ['ar-SY;q=0.5,en-GB;q=0.9', 'en'],
    'case normalized by Accept-Language' => ['EN-us', 'en'],
    'unsupported' => ['fr', 'ar'],
    'absent' => [null, 'ar'],
    'malformed' => ['../../en', 'ar'],
    'disabled language' => ['en;q=0', 'ar'],
    'out-of-range quality' => ['en;q=2', 'ar'],
    'malformed quality' => ['en;q=1oops', 'ar'],
    'valid choice after malformed weight' => ['en;q=2,ar;q=0.8', 'ar'],
    'wildcard' => ['*', 'ar'],
]);

test('nested API locale scopes restore all state after an exception and consecutive requests', function () {
    config(['app.ui_default_locale' => 'en', 'app.default_language' => 'sa', 'app.fallback_locale' => 'ar']);
    app()->setLocale('sa');
    app('translator')->setLocale('ar');
    app('translator')->setFallback('en');

    expect(fn () => StoreLocale::withLocale('en', 'en', function () {
        StoreLocale::withLocale('sa', 'ar', function () {
            throw new RuntimeException('locale scope failure');
        });
    }))->toThrow(RuntimeException::class, 'locale scope failure');

    expect(app()->getLocale())->toBe('sa')
        ->and(app('translator')->getLocale())->toBe('ar')
        ->and(app('translator')->getFallback())->toBe('en');

    foreach (['ar', null, 'en', null] as $header) {
        $request = Request::create('/temp/api/v2/auth/info');
        if ($header !== null) {
            $request->headers->set('App-Language', $header);
        }
        $response = (new AppLanguage)->handle($request, fn () => apiLocaleSnapshot());
        expect($response->getData(true)['ui'])->toBe($header ?? 'en');
    }
    expect(app()->getLocale())->toBe('sa')->and(app('translator')->getLocale())->toBe('ar');
});

test('API ban messages use files and preserve the refusal and token revocation contract', function (string $locale, string $message) {
    $banned = createStoreUser('customer');
    $banned->forceFill(['banned' => 1])->save();
    $token = storeApiToken($banned);
    $bystander = createStoreUser('customer');
    $otherToken = storeApiToken($bystander);
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();

    $this->withToken($token)->getJson('/temp/api/v2/wallet/history', ['App-Language' => $locale])
        ->assertForbidden()->assertExactJson(['result' => false, 'result_key' => 'banned', 'message' => $message]);

    expectNoStorefrontTranslationQueries();
    expect(PersonalAccessToken::findToken($token))->toBeNull()
        ->and(PersonalAccessToken::findToken($otherToken)?->tokenable_id)->toBe($bystander->id);
})->with([['en', 'You are banned'], ['ar', 'تم حظر حسابك'], ['sa', 'تم حظر حسابك']]);

test('delivery and driver API gates localize only the human refusal', function (string $middleware, string $locale, string $message) {
    $request = Request::create('/guarded');
    $customer = createStoreUser('customer');
    $request->setUserResolver(fn () => $customer);
    startStorefrontTranslationQueryLog();
    $response = StoreLocale::withLocale($locale, StoreLocale::uiLocale($locale), fn () => (new $middleware)->handle($request, fn () => response('unexpected')));

    expect($response->getStatusCode())->toBe(403)->and($response->getData(true))->toBe(['message' => $message]);
    expectNoStorefrontTranslationQueries();
})->with([
    [IsDriver::class, 'en', 'You must be a driver to access this page.'],
    [IsDriver::class, 'ar', 'يجب أن تكون سائقاً للوصول إلى هذه الصفحة.'],
    [IsDeliveryBoy::class, 'en', 'You must be a delivery boy to access this page.'],
    [IsDeliveryBoy::class, 'ar', 'يجب أن تكون مندوب توصيل للوصول إلى هذه الصفحة.'],
]);

test('warehouse token refusals preserve the legacy HTTP status and numeric body status', function (string $locale, string $message) {
    config(['app.wms_token' => 'expected-test-token']);
    $request = Request::create('/temp/api/v2/warehouse/orders');
    $request->headers->set('private-token', 'wrong-test-token');
    startStorefrontTranslationQueryLog();
    $response = StoreLocale::withLocale($locale, $locale, fn () => (new WmsAuthMiddleware)->handle($request, fn () => response('unexpected')));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['result' => false, 'message' => $message, 'status' => 401]);
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'invalid token, who are you?'],
    ['ar', 'رمز الدخول غير صالح، تعذر التحقق من هويتك.'],
]);

test('the existing API fallback keeps its envelope and 404 status with file text', function (string $locale, string $message) {
    startStorefrontTranslationQueryLog();
    $this->getJson('/temp/api/localization-missing-route', ['Accept-Language' => $locale])
        ->assertNotFound()->assertExactJson(['data' => [], 'success' => false, 'status' => 404, 'message' => $message]);
    expectNoStorefrontTranslationQueries();
})->with([['en', 'Invalid Route'], ['ar', 'المسار غير صالح']]);
