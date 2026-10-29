<?php

use App\Models\Cart;
use App\Models\Visit;
use App\Models\Wishlist;
use App\Services\TripService;
use App\Support\StoreLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config([
        'app.locale' => 'ar',
        'app.ui_default_locale' => 'ar',
        'app.fallback_locale' => 'en',
        'app.default_language' => 'en',
    ]);
});

test('mobile credential validation uses files and preserves its HTTP 200 refusal contract', function (string $locale, string $message) {
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();

    $this->withHeader('App-Language', $locale)->postJson('/temp/api/v2/auth/login', [])
        ->assertOk()->assertExactJson(['result' => false, 'message' => $message]);

    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->count())->toBe(0);
})->with([
    ['en', 'Email and Password Are Required!'],
    ['ar', 'البريد الإلكتروني وكلمة المرور مطلوبان!'],
]);

test('mobile reset validation keeps its error fields types and HTTP status in both languages', function (string $locale, string $message, string $error) {
    startStorefrontTranslationQueryLog();

    $this->withHeader('App-Language', $locale)->postJson('/temp/api/v2/auth/password/confirm_reset', [])
        ->assertUnprocessable()->assertExactJson([
            'result' => false,
            'message' => $message,
            'errors' => ['email_or_phone' => [$error]],
            'user_id' => 0,
        ]);

    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'Validation Error', 'The email or phone and the code are required.'],
    ['ar', 'خطأ في التحقق من البيانات', 'البريد الإلكتروني أو رقم الهاتف والرمز مطلوبان.'],
]);

test('wishlist messages use files while ownership identifiers booleans and create status remain unchanged', function (string $locale, string $added, string $present, string $missing, string $removed) {
    $customer = createStoreUser('customer');
    $product = createStoreProduct();
    $other = Wishlist::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id]);
    startStorefrontTranslationQueryLog();

    $this->withHeader('App-Language', $locale)->postJson('/temp/api/v2/wishlists', ['product_id' => $product->id])->assertUnauthorized();
    expect(Wishlist::count())->toBe(1);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/wishlists', ['product_id' => $product->id])
        ->assertCreated()->assertExactJson(['message' => $added]);
    $mine = Wishlist::where('user_id', $customer->id)->firstOrFail();

    $this->getJson('/temp/api/v2/wishlists-add-product?product_id='.$product->id)
        ->assertOk()->assertExactJson([
            'message' => $present,
            'is_in_wishlist' => true,
            'product_id' => $product->id,
            'wishlist_id' => $mine->id,
        ]);
    $this->deleteJson('/temp/api/v2/wishlists/'.$other->id)
        ->assertNotFound()->assertExactJson(['result' => false, 'message' => $missing]);
    $this->deleteJson('/temp/api/v2/wishlists/'.$mine->id)
        ->assertOk()->assertExactJson(['result' => true, 'message' => $removed]);

    expectNoStorefrontTranslationQueries();
    expect(Wishlist::whereKey($other->id)->exists())->toBeTrue()
        ->and(Wishlist::whereKey($mine->id)->exists())->toBeFalse();
})->with([
    ['en', 'Product is successfully added to your wishlist', 'Product present in wishlist', 'Wishlist item not found', 'Product is successfully removed from your wishlist'],
    ['ar', 'تمت إضافة المنتج إلى قائمة رغباتك بنجاح', 'المنتج موجود في قائمة الرغبات', 'لم يتم العثور على العنصر في قائمة الرغبات', 'تمت إزالة المنتج من قائمة رغباتك بنجاح'],
]);

test('cart quantity refusals interpolate the original content name without changing quantities or response shape', function (string $locale, string $prefix) {
    $name = 'Tea :count " <tag> &';
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['name' => $name, 'min_qty' => 3, 'max_qty' => 100]);
    $cart = Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 5]);
    $noise = Cart::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id, 'quantity' => 7]);
    startStorefrontTranslationQueryLog();

    $this->withToken(storeApiToken($customer))->withHeader('App-Language', $locale)
        ->postJson('/temp/api/v2/carts/process', ['cart_ids' => (string) $cart->id, 'cart_quantities' => '2'])
        ->assertOk()->assertExactJson(['result' => false, 'message' => $prefix.$name.'.']);

    expectNoStorefrontTranslationQueries();
    expect((int) $cart->fresh()->quantity)->toBe(5)
        ->and((int) $noise->fresh()->quantity)->toBe(7)
        ->and($product->fresh()->name)->toBe($name);
})->with([
    ['en', 'At least 3 items must be ordered for '],
    ['ar', 'يجب طلب 3 قطع على الأقل من '],
]);

test('cart availability refusals preserve stock count and variant content', function (string $locale, string $prefix) {
    $customer = createStoreUser('customer');
    $variant = 'Large :count " &';
    $product = createStoreProduct(['name' => 'Tea', 'min_qty' => 1, 'max_qty' => 100, 'current_stock' => 3]);
    DB::table('product_stocks')->where('product_id', $product->id)->update(['variant' => $variant]);
    $cart = Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'variation' => $variant]);
    startStorefrontTranslationQueryLog();

    $this->withToken(storeApiToken($customer))->withHeader('App-Language', $locale)
        ->postJson('/temp/api/v2/carts/process', ['cart_ids' => (string) $cart->id, 'cart_quantities' => '4'])
        ->assertOk()->assertExactJson(['result' => false, 'message' => $prefix.'Tea ('.$variant.').']);

    expectNoStorefrontTranslationQueries();
    expect((int) $cart->fresh()->quantity)->toBe(1)
        ->and((int) DB::table('product_stocks')->where('product_id', $product->id)->value('qty'))->toBe(3);
})->with([
    ['en', 'Only 3 items are available for '],
    ['ar', 'تتوفر 3 قطع فقط من '],
]);

test('database static overrides cannot replace a mobile API message', function () {
    DB::table('translations')->insert(['lang' => 'ar', 'lang_key' => 'email_and_password_are_required', 'lang_value' => 'Database override']);
    $before = DB::table('translations')->get()->toJson();
    startStorefrontTranslationQueryLog();

    $this->withHeader('App-Language', 'ar')->postJson('/temp/api/v2/auth/login', [])
        ->assertOk()->assertExactJson(['result' => false, 'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان!']);

    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->get()->toJson())->toBe($before);
});

test('a successful current-visit response retains its deliberately empty message', function (string $locale) {
    $representative = createStoreUser('customer');
    $representative->forceFill(['is_rep' => 1])->save();
    $customer = createStoreUser('customer');
    $visit = Visit::create(['rep_id' => $representative->id, 'customer_id' => $customer->id, 'note' => 'Untranslated visit note']);
    Visit::create(['rep_id' => createStoreUser('customer')->id, 'customer_id' => $customer->id, 'note' => 'Other visit']);
    startStorefrontTranslationQueryLog();

    $response = $this->withToken(storeApiToken($representative))->withHeader('App-Language', $locale)
        ->getJson('/temp/api/v2/current-visit')->assertOk()
        ->assertJsonPath('message', '')
        ->assertJsonPath('result', true)
        ->assertJsonPath('data.id', $visit->id)
        ->assertJsonPath('data.note', 'Untranslated visit note')
        ->assertJsonPath('data.customer.id', $customer->id);

    expectNoStorefrontTranslationQueries();
    expect(array_keys($response->json()))->toBe(['message', 'data', 'result']);
})->with(['en', 'ar']);

test('mobile signup validation uses the shared translated attributes and keeps validation field names', function (string $locale, string $name, string $password) {
    startStorefrontTranslationQueryLog();

    $this->withHeader('App-Language', $locale)->postJson('/temp/api/v2/auth/signup', ['register_by' => 'email'])
        ->assertOk()->assertJsonPath('result', false)
        ->assertJsonPath('errors.name.0', $name)
        ->assertJsonPath('errors.password.0', $password)
        ->assertJsonMissingPath('access_token');

    expectNoStorefrontTranslationQueries();
    expect(DB::table('users')->count())->toBe(0);
})->with([
    ['en', 'The name field is required.', 'The password field is required.'],
    ['ar', 'حقل الاسم مطلوب.', 'حقل كلمة المرور مطلوب.'],
]);

test('seller product validation keeps its array message envelope while translating fields', function (string $locale, array $messages) {
    $seller = createStoreUser('seller');
    startStorefrontTranslationQueryLog();

    $this->withToken(storeApiToken($seller))->withHeader('App-Language', $locale)
        ->postJson('/temp/api/v2/seller/products/add', [])
        ->assertUnprocessable()->assertExactJson(['message' => $messages, 'result' => false]);

    expectNoStorefrontTranslationQueries();
    expect(DB::table('products')->count())->toBe(0);
})->with([
    ['en', ['The name field is required.', 'The category field is required.', 'The unit field is required.', 'The minimum purchase quantity field is required.', 'The unit price field is required.']],
    ['ar', ['حقل الاسم مطلوب.', 'حقل الفئة مطلوب.', 'حقل الوحدة مطلوب.', 'حقل الحد الأدنى لكمية الشراء مطلوب.', 'حقل سعر الوحدة مطلوب.']],
]);

test('warehouse validation retains HTTP 200 and machine status identifiers while translating the message', function (string $locale, string $message) {
    config(['app.wms_token' => 'localization-warehouse-token']);
    startStorefrontTranslationQueryLog();

    $this->withHeaders(['private-token' => 'localization-warehouse-token', 'Accept-Language' => $locale])
        ->postJson('/temp/api/v2/warehouse/update_order', ['status' => 'shipped'])
        ->assertOk()->assertExactJson(['result' => false, 'message' => $message, 'status' => 200]);

    expectNoStorefrontTranslationQueries();
    expect(DB::table('orders')->count())->toBe(0);
})->with([
    ['en', 'status must be `fetched` or `sent` '],
    ['ar', 'يجب أن تكون قيمة status إما `fetched` أو `sent`.'],
]);

test('missing CMR checkpoints return a localized error without generating a document', function (string $locale, string $message) {
    startStorefrontTranslationQueryLog();

    $response = StoreLocale::withLocale($locale, $locale, fn () => app(TripService::class)->process_cmr_file(999999, Request::create('/')));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => $message]);
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'Checkpoint not found'],
    ['ar', 'لم يتم العثور على نقطة التحقق'],
]);

test('API catalogs have matching keys and placeholders and controllers no longer use database static translations', function () {
    $english = Arr::dot(require lang_path('en/api.php'));
    $arabic = Arr::dot(require lang_path('ar/api.php'));
    expect(array_keys($arabic))->toBe(array_keys($english));

    foreach ($english as $key => $value) {
        expect($arabic[$key])->toBeString()->not->toBe('');
        preg_match_all('/:[A-Za-z_][A-Za-z_0-9]*/', $value, $en);
        preg_match_all('/:[A-Za-z_][A-Za-z_0-9]*/', $arabic[$key], $ar);
        $en = array_values(array_unique($en[0]));
        $ar = array_values(array_unique($ar[0]));
        sort($en);
        sort($ar);
        expect($ar)->toBe($en);
    }

    foreach (File::allFiles(app_path('Http/Controllers/Api/V2')) as $file) {
        expect(file_get_contents($file->getPathname()))->not->toMatch('/\btranslate\s*\(/');
    }
    expect(file_get_contents(app_path('Utility/NotificationUtility.php')))->not->toMatch('/\btranslate\s*\(/');
});
