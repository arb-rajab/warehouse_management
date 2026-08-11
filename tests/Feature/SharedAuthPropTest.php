<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the shared auth prop exposes only the fields the UI reads', function () {
    $user = actingAsAdmin();

    $response = $this->get('/admin/rows');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Index')
            ->has('auth.user', 3)
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.email_verified_at')
    );
});

test('the shared auth prop is null for a guest', function () {
    $response = $this->get('/login');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Auth/Login')
            ->where('auth.user', null)
    );
});
