<?php

use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('a user with must_change_password set is redirected to the change-password page when visiting the admin panel', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertRedirect(route('password.change'));
});

test('a user with must_change_password cleared can access the admin panel normally', function () {
    $user = User::factory()->create(['must_change_password' => false]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
});

test('a user with must_change_password set can still view the change-password page itself', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->get('/password/change');

    $response->assertOk();
});

test('a user with must_change_password set can still log out instead of changing their password', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a user with must_change_password set can still switch the UI language', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->post('/locale/ar')->assertRedirect();

    expect(session('locale'))->toBe('ar');
});

test('a user with must_change_password set can still submit the change-password form as an inertia-style ajax request', function () {
    $user = User::factory()->create(['must_change_password' => true, 'password' => 'old-password']);

    $response = $this->actingAs($user)->withHeaders(inertiaHeaders())->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    expect($user->fresh()->must_change_password)->toBeFalse();
});

test('a guest is unaffected by the middleware', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
});

test('a freshly created user instance relying on the schema default, never re-fetched from the database, still passes through the middleware', function () {
    // must_change_password has no factory override here on purpose: Eloquent
    // does not re-select a DB-defaulted column after an insert, so a model
    // instance built this way and handed straight to actingAs() (the same
    // shape actingAsAdmin() produces) never had the attribute hydrated at
    // all. Touching it under Eloquent strict mode (local/testing) used to
    // throw MissingAttributeException instead of reading the false default.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
});

test('a StoreUser passes through untouched, since must_change_password is a WMS-only column', function () {
    // Under /temp, App\Http\Middleware\UseStoreAuthGuard makes the
    // authenticated user a StoreUser, not App\Models\User — a model with no
    // must_change_password column at all, so accessing it under this app's
    // strict-attributes mode would throw MissingAttributeException rather
    // than returning null. This is a direct middleware-instantiation test
    // (see TelescopeAccessTest's own pattern) rather than a real /temp
    // route, since satisfying every other legacy role/verification
    // middleware isn't this middleware's own concern.
    $storeUser = StoreUser::create([
        'name' => 'Store Customer', 'email' => 'store-customer@example.com', 'user_type' => 'customer', 'phone' => '5555555555',
    ]);

    $request = Request::create('/temp/dashboard');
    $request->setUserResolver(fn () => $storeUser);

    $called = false;
    (new EnsurePasswordHasBeenChanged)->handle($request, function () use (&$called) {
        $called = true;

        return new Response('ok');
    });

    expect($called)->toBeTrue();
});
