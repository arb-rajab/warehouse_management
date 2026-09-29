<?php

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
