<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the locale toggle route stores the choice in the session and it is reflected in later requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale/ar')->assertRedirect();

    expect(session('locale'))->toBe('ar');

    $response = $this->actingAs($user)->get('/admin/rows');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('locale', 'ar')
    );
});

test('the default locale is english when nothing has been chosen', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/rows');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('locale', 'en')
    );
});

test('an unsupported locale is rejected and the session is left untouched', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale/fr')->assertNotFound();

    expect(session('locale'))->toBeNull();
});

test('a guest can toggle the locale from the login screen', function () {
    $this->post('/locale/ar')->assertRedirect();

    expect(session('locale'))->toBe('ar');
});
