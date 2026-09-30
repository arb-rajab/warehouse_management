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
 * The home page (HomeController::index()) caches featured_categories/
 * todays_deal_products/newest_products as raw Eloquent collections, which
 * hit the exact same config/cache.php serializable_classes allow-list
 * problem get_setting()/addon_is_activated() did — except here every
 * consumer downstream (this view and whatever it includes) expects real
 * Category/Product model instances, not arrays, so the fix caches plain
 * attribute arrays and rehydrates them with Model::hydrate() on every read
 * instead. Requesting it twice is what actually exercises the bug: the
 * first request computes and writes the cache, the second reads it back —
 * only the second failed before the fix.
 *
 * Deliberately seeds no Product rows: several of the columns
 * filter_products()/HomeController::index() filter by (todays_deal,
 * isNewProduct, auction_product, approved, added_by) don't exist on this
 * app's local products stand-in — a pre-existing gap this pass didn't
 * touch, see products.category_id's own migration for the pattern were it
 * ever tackled. Whether a query referencing one of them throws depends on
 * sqlite's query planner and, empirically, on whether the table already
 * has matching rows — not something to depend on. An empty table is the
 * one state confirmed safe.
 */
test('the legacy home page renders successfully, both freshly cached and read back from cache', function () {
    $this->get('/temp')->assertOk();
    $this->get('/temp')->assertOk();
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
