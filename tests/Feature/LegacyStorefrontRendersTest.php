<?php

use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for the legacy storefront actually rendering under
 * /temp — see .ai/rules/legacy-albaraka-holland.md requirement 5. Mounting
 * the legacy routes made them reachable for the first time since the merge,
 * which is exactly what surfaced the bugs this test guards against: every
 * one of the fixes it covers (app/Http/Helpers.php's hex2rgba()/get_setting()/
 * addon_is_activated()/getBaseURL(), the Language-row null-safety in
 * shop/frontend/layouts/app.blade.php, the header_menu_labels/links
 * defaults in shop/frontend/inc/nav.blade.php, and the Route::has() guard in
 * shop/frontend/partials/modal.blade.php) was invisible until a real request
 * actually rendered this layout — nothing else in this app exercises it.
 */
test('the legacy login page renders successfully', function () {
    $response = $this->get('/temp/users/login');

    $response->assertOk();
    $response->assertSee('name="password"', false);
});

test('the legacy registration page renders successfully', function () {
    $response = $this->get('/temp/users/registration');

    $response->assertOk();
});

/**
 * Each of the 5 policy pages reads its Page row's `keywords`/`meta_image`
 * columns — see PageSeeder's own docblock for why this needed both seed
 * data and an attribute-name fix (the views read `tags`/`meta_img`, columns
 * that don't exist on this table) before any of them could render at all.
 */
test('each legacy policy page renders successfully once seeded', function (string $uri) {
    $this->seed(PageSeeder::class);

    $response = $this->get($uri);

    $response->assertOk();
})->with([
    '/temp/terms',
    '/temp/privacy-policy',
    '/temp/return-policy',
    '/temp/seller-policy',
    '/temp/support-policy',
]);

/**
 * The home page reads today's deal and the newest products through
 * filter_products(), which filters on columns (todays_deal, isNewProduct,
 * auction_product, approved, added_by, user_id) the `products` table here
 * lacked until the store's own columns were carried over: with no product
 * rows, sqlite read the unqualified ones as string literals rather than
 * failing, which is why a page with products had never been rendered.
 * Requesting it twice reads the cached lists back.
 */
test("the legacy home page shows today's deal and the newest products, freshly cached and from cache", function () {
    seedStoreCurrency();
    $deal = createStoreProduct(['todays_deal' => 1]);
    $newest = createStoreProduct(['isNewProduct' => true]);
    $unpublished = createStoreProduct(['todays_deal' => 1, 'isNewProduct' => true, 'published' => false]);

    foreach ([1, 2] as $request) {
        $this->get('/temp')
            ->assertOk()
            ->assertSee($deal->name)
            ->assertSee($newest->name)
            ->assertDontSee($unpublished->name);
    }
});

/**
 * todays_deal() cached the models themselves under the key the home page
 * caches attribute arrays under, so whichever page ran second read the
 * other's shape back.
 */
test("the today's deal page and the home page share their cached list", function () {
    seedStoreCurrency();
    $deal = createStoreProduct(['todays_deal' => 1]);

    $this->get('/temp')->assertOk();
    $this->get('/temp/todays-deal')->assertOk()->assertSee($deal->name);
    $this->get('/temp')->assertOk()->assertSee($deal->name);
});

/**
 * Production's cache serializes through config/cache.php's
 * serializable_classes allow-list, which a cached model does not survive:
 * the default currency every price goes through, and the product lists the
 * home page sections cache, came back as __PHP_Incomplete_Class on the second
 * request that read them.
 */
test('the storefront keeps working once its cached models are read back from a serializing cache', function () {
    useSerializingCache();
    seedStoreCurrency();
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);
    DB::table('business_settings')->insert(['type' => 'home_categories', 'value' => json_encode([(string) $category])]);
    $product = createStoreProduct(['todays_deal' => 1, 'featured' => 1, 'category_id' => $category]);

    foreach ([1, 2] as $request) {
        $this->get('/temp')->assertOk()->assertSee($product->name);
        $this->get('/temp/todays-deal')->assertOk()->assertSee($product->name);
        $this->get('/temp/product/'.$product->slug)->assertOk()->assertSee($product->name);
        $this->post('/temp/home/section/featured')->assertOk()->assertSee($product->name);
        $this->post('/temp/home/section/home_categories')->assertOk()->assertSee($product->name);
        $this->post('/temp/home/section/best_selling')->assertOk();
        $this->post('/temp/home/section/best_sellers')->assertOk();
    }
});

/**
 * laravel/ui's own traits render `auth.login`, `auth.register`,
 * `auth.passwords.email` and `auth.passwords.reset`; the store's views live
 * under `shop.` here, and the sweep that prefixed every view() call never
 * reached vendor/, so each of these 500'd until the controllers overrode them.
 */
test('the laravel/ui auth pages render', function (string $uri) {
    $this->get($uri)->assertOk();
})->with([
    '/temp/login',
    '/temp/register',
    '/temp/password/reset',
    '/temp/password/reset/some-token',
]);

/**
 * These two share the older backend layout (shop.backend.layouts.layout),
 * whose RTL check read `Language::first()->rtl` with no language row, and
 * post to `route('login')`, which is this app's own admin login.
 */
test('the seller and delivery boy login pages render and post to the store login', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertSee('action="'.route('shop.login').'"', false);
})->with([
    '/temp/seller/login',
    '/temp/deliveryboy/login',
]);

/**
 * The footer read `Auth::user()->photo`, a column the store has never had:
 * a null the legacy app never noticed, and an exception under this app's
 * strict mode on every page a signed-in customer opened. Its avatar branch
 * called `custom_asset()`, which exists nowhere, so it now uses the
 * `avatar_original` upload the header already shows.
 */
test('a storefront page renders for a signed-in customer, with their avatar', function () {
    $customer = createStoreUser('customer');
    $uploadId = DB::table('uploads')->insertGetId(['user_id' => $customer->id, 'file_name' => 'uploads/all/avatar.png']);
    $customer->forceFill(['avatar_original' => $uploadId])->save();
    actingAsStoreUser($customer);

    $this->get('/temp/brands')
        ->assertOk()
        ->assertSee(my_asset('uploads/all/avatar.png'));
});

/**
 * The compare list is kept in the session, which this app serializes as JSON
 * (config/session.php): the Collection the legacy code stored came back as a
 * plain array, so the second product anyone compared 500'd. Sending the
 * session cookie makes each request reload the session from its store, as a
 * browser's does, rather than reuse the test harness's in-memory copy.
 */
test('comparing keeps the last three products across requests', function () {
    foreach ([1, 2, 3, 4, 2] as $productId) {
        $this->withCookie(config('session.cookie'), session()->getId())
            ->post('/temp/compare/addToCompare', ['id' => $productId])
            ->assertOk();
    }

    expect(session('compare'))->toBe([2, 3, 4]);
});
