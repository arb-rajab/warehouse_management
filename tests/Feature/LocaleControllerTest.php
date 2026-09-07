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

test('a stale or invalid locale value already in the session falls back to the default', function () {
    // SetLocale does Locale::tryFrom((string) session('locale'))->value ?? config('app.locale')
    // -- property access on a possibly-null enum result, which only "works" because
    // PHP degrades null property access to a warning that ?? then catches. This pins
    // that the fallback actually resolves to the app default, not just that nothing
    // fatals, for a value left over after e.g. a supported locale is removed.
    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'zz'])->actingAs($user)->get('/admin/rows');

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
