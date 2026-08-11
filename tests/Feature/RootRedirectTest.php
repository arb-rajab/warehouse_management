<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a guest visiting the root is sent to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('a signed-in admin visiting the root lands on the row list', function () {
    actingAsAdmin();

    $response = $this->followingRedirects()->get('/');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Index')
    );
});

test('a signed-in admin visiting the admin index lands on the row list', function () {
    actingAsAdmin();

    $response = $this->followingRedirects()->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Index')
    );
});

test('a mobile app user cannot reach the admin index', function () {
    $mobileUser = User::factory()->mobileUser()->create();

    $response = $this->actingAs($mobileUser)->get('/admin');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when visiting the admin index', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login'));
});
