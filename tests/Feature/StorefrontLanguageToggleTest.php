<?php

use App\Support\StoreLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * The storefront header's Arabic/English toggle (POST /temp/language/toggle,
 * `language.toggle`) and the Language middleware that applies its choice.
 */

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.ui_default_locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
});

function showStorefrontLanguageToggle(): void
{
    DB::table('business_settings')->insert(['type' => 'show_language_switcher', 'value' => 'on']);
}

test('the toggle route is a POST inside the web group, so CSRF verification applies', function () {
    $route = Route::getRoutes()->getByName('language.toggle');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('temp/language/toggle')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->gatherMiddleware())->toContain('web');
});

test('a locale outside ar and en is rejected and nothing is stored', function (mixed $locale) {
    $response = $this->from('/temp/users/login')
        ->post('/temp/language/toggle', ['locale' => $locale, 'redirect' => '/temp/users/login']);

    $response->assertRedirect('/temp/users/login')
        ->assertSessionHasErrors('locale')
        ->assertSessionMissing('locale')
        ->assertCookieMissing(StoreLocale::COOKIE);
})->with([
    'another language' => 'fr',
    'legacy content code' => 'sa',
    'wrong case' => 'AR',
    'empty' => '',
    'array' => [['ar']],
]);

test('a missing locale is rejected and nothing is stored', function () {
    $this->from('/temp/users/login')->post('/temp/language/toggle', ['redirect' => '/temp/users/login'])
        ->assertSessionHasErrors('locale')
        ->assertSessionMissing('locale')
        ->assertCookieMissing(StoreLocale::COOKIE);
});

test('a valid locale is stored in the session and a long-lived cookie, then the page is shown again', function () {
    $response = $this->post('/temp/language/toggle', ['locale' => 'en', 'redirect' => '/temp/users/login']);

    $response->assertRedirect(url('/temp/users/login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('locale', 'en')
        ->assertCookie(StoreLocale::COOKIE, 'en');
    expect($response->getCookie(StoreLocale::COOKIE, false)->getExpiresTime())
        ->toBeGreaterThan(now()->addDays(360)->getTimestamp());
});

test('the redirect keeps the page query string', function () {
    $this->post('/temp/language/toggle', ['locale' => 'en', 'redirect' => '/temp/search?keyword=tea&page=2'])
        ->assertRedirect(url('/temp/search?keyword=tea&page=2'));
});

test('a redirect that could leave the site goes to the storefront home instead', function (?string $redirect) {
    $payload = ['locale' => 'en'];
    if ($redirect !== null) {
        $payload['redirect'] = $redirect;
    }

    $response = $this->post('/temp/language/toggle', $payload);

    $response->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'en')
        ->assertCookie(StoreLocale::COOKIE, 'en');
    expect($response->headers->get('Location'))->toStartWith(url('/'));
})->with([
    'absolute url' => 'https://evil.example/temp',
    'protocol-relative url' => '//evil.example/temp',
    'backslash host' => '/\\evil.example/temp',
    'relative path' => 'evil.example/temp',
    'control character' => "/\t/evil.example",
    'no redirect' => [null],
]);

test('Arabic is stored as the active language row mapped to ar, such as the legacy sa row', function () {
    DB::table('languages')->insert([
        ['name' => 'Disabled Arabic', 'code' => 'xa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 0],
        ['name' => 'العربية', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1],
    ]);

    $this->post('/temp/language/toggle', ['locale' => 'ar', 'redirect' => '/temp/users/login'])
        ->assertSessionHas('locale', 'sa')
        ->assertCookie(StoreLocale::COOKIE, 'sa');
});

test('a language row whose code is the locale itself wins over one only mapped to it', function () {
    DB::table('languages')->insert([
        ['name' => 'Persian', 'code' => 'fa', 'app_lang_code' => 'en', 'rtl' => 1, 'status' => 1],
        ['name' => 'English', 'code' => 'en', 'app_lang_code' => 'en', 'rtl' => 0, 'status' => 1],
    ]);

    $this->post('/temp/language/toggle', ['locale' => 'en', 'redirect' => '/temp/users/login'])
        ->assertSessionHas('locale', 'en');
});

test('without a language row the bare locale is stored', function () {
    $this->post('/temp/language/toggle', ['locale' => 'ar', 'redirect' => '/temp/users/login'])
        ->assertSessionHas('locale', 'ar')
        ->assertCookie(StoreLocale::COOKIE, 'ar');
});

test('the stored cookie sets the storefront language on a later visit without writing the session', function () {
    $this->withCookie(StoreLocale::COOKIE, 'en')->get('/temp/users/login')->assertOk()
        ->assertSee('lang="en" dir="ltr"', false)
        ->assertSessionMissing('locale');
});

test('an explicit session choice wins over the cookie', function () {
    $this->withSession(['locale' => 'ar'])->withCookie(StoreLocale::COOKIE, 'en')->get('/temp/users/login')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false);
});

test('an unsupported cookie value falls back to the configured default', function () {
    $this->withCookie(StoreLocale::COOKIE, 'xx')->get('/temp/users/login')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false);
});

test('the store api keeps following App-Language, not the storefront cookie', function () {
    $this->withCookie(StoreLocale::COOKIE, 'ar')->withHeaders(['App-Language' => 'en'])
        ->postJson('/api/v1/store/auth/info')->assertOk()
        ->assertJsonPath('message', 'Login Failed');
});

test('the store api default ignores the storefront cookie', function () {
    config(['app.ui_default_locale' => 'en', 'app.default_language' => 'en']);

    $this->withCookie(StoreLocale::COOKIE, 'ar')->postJson('/api/v1/store/auth/info')->assertOk()
        ->assertJsonPath('message', 'Login Failed');
});

test('the header shows the toggle for English on Arabic pages, on desktop and in the mobile menu', function () {
    showStorefrontLanguageToggle();

    $html = $this->get('/temp/users/login')->assertOk()
        ->assertSee('action="'.route('language.toggle').'"', false)
        ->assertSee('<input type="hidden" name="locale" value="en">', false)
        ->assertSee('<input type="hidden" name="redirect" value="/temp/users/login">', false)
        ->assertSee('<span lang="en" dir="ltr">English</span>', false)
        ->assertSee('aria-label="تغيير لغة المتجر إلى English"', false)
        ->assertSee('name="_token"', false)
        ->getContent();

    expect(substr_count($html, 'data-language-toggle'))->toBe(2)
        ->and($html)->not->toContain('id="lang-change"');
});

test('the header shows the toggle for Arabic on English pages', function () {
    showStorefrontLanguageToggle();

    $html = $this->withSession(['locale' => 'en'])->get('/temp/users/login')->assertOk()
        ->assertSee('<input type="hidden" name="locale" value="ar">', false)
        ->assertSee('<span lang="ar" dir="rtl">العربية</span>', false)
        ->assertSee('aria-label="Switch the store language to العربية"', false)
        ->getContent();

    expect(substr_count($html, 'data-language-toggle'))->toBe(2);
});

test('the header hides the toggle while the language switcher setting is off', function () {
    $this->get('/temp/users/login')->assertOk()->assertDontSee('data-language-toggle', false);
});

test('choosing English with the toggle renders the next page in English', function () {
    showStorefrontLanguageToggle();

    $this->post('/temp/language/toggle', ['locale' => 'en', 'redirect' => '/temp/users/login'])
        ->assertRedirect(url('/temp/users/login'));

    $this->get('/temp/users/login')->assertOk()
        ->assertSee('lang="en" dir="ltr"', false)
        ->assertSee('<input type="hidden" name="locale" value="ar">', false);
});
