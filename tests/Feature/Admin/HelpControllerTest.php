<?php

use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the help landing page', function () {
    actingAsAdmin();

    $response = $this->get('/admin/help');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Help/Index')
    );
});

test('an authenticated admin can view each help topic page', function () {
    actingAsAdmin();

    $topics = [
        'rows' => 'Admin/Help/Rows',
        'users' => 'Admin/Help/Users',
        'products' => 'Admin/Help/Products',
        'cell-logs' => 'Admin/Help/CellLogs',
    ];

    foreach ($topics as $slug => $component) {
        $response = $this->get("/admin/help/{$slug}");

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page->component($component)
        );
    }
});

test('an unknown help topic is not found', function () {
    actingAsAdmin();

    $response = $this->get('/admin/help/not-a-real-topic');

    $response->assertNotFound();
});

test('a mobile app user cannot view the help landing page', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/help');

    $response->assertForbidden();
});

test('a mobile app user cannot view a help topic page', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/help/rows');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when visiting help', function () {
    $response = $this->get('/admin/help');

    $response->assertRedirect(route('login'));
});
