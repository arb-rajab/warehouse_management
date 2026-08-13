<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Auth/Login')
    );
});

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('logging in with the wrong password is rejected', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('logging in with an unknown email is rejected', function () {
    $response = $this->post('/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('logging in with an overly long email or password is rejected', function () {
    $response = $this->post('/login', [
        'email' => str_repeat('a', 256).'@example.com',
        'password' => str_repeat('a', 256),
    ]);

    $response->assertSessionHasErrors(['email', 'password']);
    $this->assertGuest();
});

test('a mobile app user cannot log in to the admin panel', function () {
    $mobileUser = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    $response = $this->post('/login', [
        'email' => $mobileUser->email,
        'password' => 'correct-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a logged-in user visiting the login page is redirected away', function () {
    actingAsAdmin();

    $response = $this->get('/login');

    $response->assertRedirect(route('admin.dashboard'));
});

test('a logged-in user can log out', function () {
    actingAsAdmin();

    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
