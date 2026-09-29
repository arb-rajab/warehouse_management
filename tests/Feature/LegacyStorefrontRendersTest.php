<?php

use Database\Seeders\PageSeeder;

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
