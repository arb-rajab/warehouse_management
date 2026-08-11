<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

test('isAdmin is true for a user with the admin role', function () {
    $admin = User::factory()->create();

    expect($admin->isAdmin())->toBeTrue();
});

test('isAdmin is false for a mobile user with no roles', function () {
    $mobileUser = User::factory()->mobileUser()->create();

    expect($mobileUser->isAdmin())->toBeFalse();
});

test('the email_verified_at attribute is cast to a datetime', function () {
    $user = User::factory()->create(['email_verified_at' => '2026-01-01 10:00:00']);

    expect($user->fresh()->email_verified_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('the password is stored hashed rather than in plain text', function () {
    $user = User::factory()->create(['password' => 'plain-text-password']);

    expect($user->fresh()->password)->not->toBe('plain-text-password');
    expect(Hash::check('plain-text-password', $user->fresh()->password))->toBeTrue();
});

test('password and remember_token are hidden from array and json representations', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
});
