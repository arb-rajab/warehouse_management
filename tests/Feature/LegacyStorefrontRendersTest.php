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
