<?php

/**
 * The merged legacy store is mounted under /temp in this one app (see
 * .ai/rules/legacy-albaraka-holland.md, "Routes"), next to the warehouse
 * panel. These pin that the store's public entry points answer successfully
 * under the single-app setup and that `/` and `/admin/login` are untouched.
 * RootRedirectTest owns the `/` redirect itself.
 */
test('the legacy home page is reachable at /temp', function () {
    seedStoreCurrency();

    $this->get('/temp')->assertOk();
});

test("the legacy admin login route is reachable and posts to the store's own login", function () {
    $this->get('/temp/login')
        ->assertOk()
        ->assertSee('name="password"', false);

    expect(route('shop.login', [], false))->toBe('/temp/login');
});

test('the legacy admin area sends a guest to the store login, not the warehouse login', function () {
    $this->get('/temp/admin')->assertRedirect(route('shop.login'));
});

test('a signed-in store admin reaches the legacy admin area', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/roles/create')->assertOk();
});

test("the warehouse panel's own login page is unaffected by the /temp mount", function () {
    $this->get('/admin/login')->assertOk();

    expect(route('login', [], false))->toBe('/admin/login');
});
