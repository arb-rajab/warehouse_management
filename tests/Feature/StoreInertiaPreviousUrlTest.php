<?php

use App\Models\Product;

/**
 * Every request below passes its headers as the call's own argument rather than
 * through `withHeaders()`, which merges into the test instance's *default*
 * headers and so leaks them into every later request in the same test. That is
 * not a style preference here: a search XHR that inherits `X-Inertia: true`
 * from an earlier page visit is an Inertia visit as far as this middleware is
 * concerned, and the test then cannot tell the two apart.
 */
test('an ordinary Inertia page visit records the previous URL', function () {
    actingAsAdmin();

    $this->get('/admin/users/create', inertiaHeaders())->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('a hard browser page load records the previous URL', function () {
    actingAsAdmin();

    $this->get('/admin/users/create')->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('an Inertia partial reload does not overwrite the previous URL', function () {
    actingAsAdmin();

    $this->get('/admin/users', inertiaHeaders())->assertOk();

    // A props-only refetch of the page the user is already on (pagination,
    // polling) is not a navigation, so it must not become the back() target.
    $this->get('/admin/users?page=2', inertiaHeaders() + [
        'X-Inertia-Partial-Data' => 'users',
        'X-Inertia-Partial-Component' => 'Admin/Users/Index',
    ])->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users'));
});

test('a prefetch does not overwrite the previous URL', function () {
    actingAsAdmin();

    $this->get('/admin/users/create', inertiaHeaders())->assertOk();

    $this->get('/admin/users', inertiaHeaders() + ['Purpose' => 'prefetch'])->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('a non-Inertia XHR to a JSON endpoint does not overwrite the previous URL', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Rice']);

    $this->get('/admin/users/create', inertiaHeaders())->assertOk();

    // ProductSelect's search goes through Inertia v3's useHttp, whose
    // XhrHttpClient sets X-Requested-With and never X-Inertia, and the action
    // returns a bare array (JSON). Laravel's own storeCurrentUrl() would have
    // skipped this request; this middleware has to skip it too.
    $this->getJson('/admin/products/search?q=ric', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('a failed form submission after a product-search XHR redirects to the form, not the JSON endpoint', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Rice']);

    // The last hard page load lands on the dashboard, as it does right after
    // login — the stale value this middleware exists to replace.
    $this->get('/admin');

    $this->get('/admin/users/create', inertiaHeaders())->assertOk();

    $this->getJson('/admin/products/search?q=ric', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ], inertiaHeaders());

    $response->assertRedirect(route('admin.users.create'));
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('wms_users', 1);
});
