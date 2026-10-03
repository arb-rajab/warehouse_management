<?php

use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * POST profile/update changed the password on the strength of the bearer token
 * alone, and took any email or phone, another customer's included. It now
 * asks for the current password to set a new one and refuses a duplicate.
 */
function updateProfile(StoreUser $user, array $data)
{
    return test()->withToken(storeApiToken($user))->postJson('/temp/api/v2/profile/update', $data);
}

test('a profile update without a token is refused and changes nothing', function () {
    $user = createStoreUser('customer');

    $this->postJson('/temp/api/v2/profile/update', ['name' => 'Hijacked'])->assertUnauthorized();

    expect($user->fresh()->name)->not->toBe('Hijacked');
});

test('a new password needs the current one', function () {
    $user = createStoreUser('customer', 'owner@example.com', 'current-password');

    updateProfile($user, ['password' => 'new-password1'])
        ->assertUnprocessable()
        ->assertJsonPath('result', false)
        ->assertJsonValidationErrors('current_password', 'errors');

    expect(Hash::check('current-password', $user->fresh()->password))->toBeTrue();
});

test('a new password is refused when the current password is wrong', function () {
    $user = createStoreUser('customer', 'owner@example.com', 'current-password');

    updateProfile($user, ['password' => 'new-password1', 'current_password' => 'not-the-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password', 'errors');

    expect(Hash::check('current-password', $user->fresh()->password))->toBeTrue();
});

test('the password changes when the current one is given', function () {
    $user = createStoreUser('customer', 'owner@example.com', 'current-password');

    updateProfile($user, ['password' => 'new-password1', 'current_password' => 'current-password'])
        ->assertOk()
        ->assertJsonPath('result', true);

    expect(Hash::check('new-password1', $user->fresh()->password))->toBeTrue();
});

test('a profile update that leaves the password alone does not ask for it', function () {
    $user = createStoreUser('customer', 'owner@example.com', 'current-password');

    updateProfile($user, ['name' => 'Renamed'])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed')
        ->and(Hash::check('current-password', $user->fresh()->password))->toBeTrue();
});

test('an email another customer already has is refused and nothing changes', function () {
    createStoreUser('customer', 'taken@example.com');
    $user = createStoreUser('customer', 'owner@example.com');

    updateProfile($user, ['name' => 'Renamed', 'email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email', 'errors');

    expect($user->fresh()->email)->toBe('owner@example.com')
        ->and($user->fresh()->name)->not->toBe('Renamed');
});

test('a phone number another customer already has is refused and nothing changes', function () {
    $other = createStoreUser('customer');
    $user = createStoreUser('customer', 'owner@example.com');
    $phone = $user->phone;

    updateProfile($user, ['phone' => $other->phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone', 'errors');

    expect($user->fresh()->phone)->toBe($phone);
});

test('a customer can resubmit their own email and phone number', function () {
    $user = createStoreUser('customer', 'owner@example.com');

    updateProfile($user, ['email' => 'owner@example.com', 'phone' => $user->phone, 'name' => 'Renamed'])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed');
});

test('a malformed email is refused', function () {
    $user = createStoreUser('customer', 'owner@example.com');

    updateProfile($user, ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email', 'errors');

    expect($user->fresh()->email)->toBe('owner@example.com');
});

test('a new email is unverified until the emailed code is used', function () {
    Notification::fake();
    $user = createStoreUser('customer', 'owner@example.com');

    updateProfile($user, ['email' => 'new@example.com'])->assertOk();

    expect($user->fresh()->email)->toBe('new@example.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, AppEmailVerificationNotification::class);
    $this->assertDatabaseHas('wms_store_verification_codes', ['user_id' => $user->id]);
});
