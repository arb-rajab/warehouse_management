<?php

use App\Enums\HelpTopic;
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

    // Iterates HelpTopic::cases() itself, rather than a hardcoded slug list,
    // so a case added to the enum without a matching arm in
    // HelpController::show()'s match (an UnhandledMatchError, surfaced here
    // as a non-200 response) fails this test instead of the suite staying
    // green with the new case silently untested.
    foreach (HelpTopic::cases() as $topic) {
        $response = $this->get("/admin/help/{$topic->value}");

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page->component("Admin/Help/{$topic->name}")
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

test('an unauthenticated caller is redirected to login when visiting a help topic page', function () {
    $response = $this->get('/admin/help/rows');

    $response->assertRedirect(route('login'));
});
