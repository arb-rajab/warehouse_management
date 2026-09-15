<?php

use App\Models\User;

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

test('a guest is unaffected by the middleware', function () {
    $response = $this->get('/login');

    $response->assertOk();
});
