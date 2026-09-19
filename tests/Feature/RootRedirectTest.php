<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a guest visiting the root is redirected to the external site', function () {
    $response = $this->get('/');

    $response->assertRedirect('https://albaraka-holland.nl/');
});

test('a signed-in admin visiting the root is redirected to the external site', function () {
    actingAsAdmin();

    $response = $this->get('/');

    $response->assertRedirect('https://albaraka-holland.nl/');
});

test('a signed-in admin visiting the admin index lands on the dashboard', function () {
    actingAsAdmin();

    $response = $this->followingRedirects()->get('/admin');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Dashboard/Index')
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
