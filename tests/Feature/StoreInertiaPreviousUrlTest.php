<?php

use App\Models\Product;

test('an ordinary Inertia page visit records the previous URL', function () {
    actingAsAdmin();

    $this->withHeaders(inertiaHeaders())->get('/admin/users/create')->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('a hard browser page load records the previous URL', function () {
    actingAsAdmin();

    $this->get('/admin/users/create')->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('an Inertia partial reload does not overwrite the previous URL', function () {
    actingAsAdmin();

    $this->withHeaders(inertiaHeaders())->get('/admin/users')->assertOk();

    // A props-only refetch of the page the user is already on (pagination,
    // polling) is not a navigation, so it must not become the back() target.
    $this->withHeaders(inertiaHeaders() + [
        'X-Inertia-Partial-Data' => 'users',
        'X-Inertia-Partial-Component' => 'Admin/Users/Index',
    ])->get('/admin/users?page=2')->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users'));
});

test('a non-Inertia XHR to a JSON endpoint does not overwrite the previous URL', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Rice']);

    $this->withHeaders(inertiaHeaders())->get('/admin/users/create')->assertOk();

    // ProductSelect's search goes through Inertia v3's useHttp, whose
    // XhrHttpClient sets X-Requested-With and never X-Inertia, and the action
    // returns a bare array (JSON). Laravel's own storeCurrentUrl() would have
    // skipped this request; this middleware has to skip it too.
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson('/admin/products/search?q=ric')
        ->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});

test('a failed form submission after a product-search XHR redirects to the form, not the JSON endpoint', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Rice']);

    // The last hard page load lands on the dashboard, as it does right after
    // login — the stale value this middleware exists to replace.
    $this->get('/admin');

    $this->withHeaders(inertiaHeaders())->get('/admin/users/create')->assertOk();

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson('/admin/products/search?q=ric')
        ->assertOk();

    $response = $this->withHeaders(inertiaHeaders())->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.create'));
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('wms_users', 1);
});

test('a prefetch does not overwrite the previous URL', function () {
    actingAsAdmin();

    $this->withHeaders(inertiaHeaders())->get('/admin/users/create')->assertOk();

    $this->withHeaders(inertiaHeaders() + ['Purpose' => 'prefetch'])
        ->get('/admin/users')
        ->assertOk();

    expect(session('_previous.url'))->toBe(url('/admin/users/create'));
});
