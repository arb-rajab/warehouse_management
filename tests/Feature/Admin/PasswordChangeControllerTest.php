<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the change password screen can be rendered', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->get('/password/change');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Auth/ChangePassword')
    );
});

test('an unauthenticated caller is redirected to login when viewing the change password page', function () {
    $response = $this->get('/password/change');

    $response->assertRedirect(route('login'));
});

test('an unauthenticated caller cannot submit the change password form', function () {
    $response = $this->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a user can change their password, which clears the must_change_password flag', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeFalse();
    expect(Hash::check('new-strong-password', $fresh->password))->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeFalse();
});

test('changing the password redirects away once the flag is cleared', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response = $this->actingAs($user->fresh())->get('/admin');

    $response->assertOk();
});

test('submitting a mismatched password confirmation is rejected and nothing changes', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting an overly short password is rejected and nothing changes', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting the wrong current password is rejected and nothing changes', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put('/password/change', [
        'current_password' => 'not-the-right-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertSessionHasErrors('current_password');

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting without a current password is rejected and nothing changes', function () {
    $user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put('/password/change', [
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertSessionHasErrors('current_password');

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});
