<?php

use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
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

test('filterOptions returns every user ordered by name, carrying only id and name', function () {
    $charlie = User::factory()->create(['name' => 'Charlie']);
    $alpha = User::factory()->mobileUser()->create(['name' => 'Alpha']);
    $bravo = User::factory()->create(['name' => 'Bravo']);

    $options = User::filterOptions();

    expect($options->pluck('name')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
    expect($options->pluck('id')->all())->toBe([$alpha->id, $bravo->id, $charlie->id]);
    expect(array_keys($options->first()->getAttributes()))->toBe(['id', 'name']);
});

test('filterOptions returns an empty collection when there are no users', function () {
    expect(User::filterOptions())->toBeEmpty();
});

test('hasHistory is false for a user with no recorded activity', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    CellStatusLog::factory()->create(['user_id' => $otherUser->id]);

    expect($user->hasHistory())->toBeFalse();
});

test('hasHistory is true for a user with a cell status log', function () {
    $user = User::factory()->create();
    CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($user->hasHistory())->toBeTrue();
});

test('hasHistory is true for a user with a verification round', function () {
    $user = User::factory()->create();
    CellVerificationRound::factory()->create(['user_id' => $user->id]);

    expect($user->hasHistory())->toBeTrue();
});

test('hasHistory is true for a user with a verification report', function () {
    $user = User::factory()->create();
    CellVerificationReport::factory()->create(['user_id' => $user->id]);

    expect($user->hasHistory())->toBeTrue();
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

test('must_change_password is cast to a boolean and defaults to false', function () {
    $user = User::factory()->create();

    expect($user->fresh()->must_change_password)->toBeFalse();

    $user->must_change_password = true;
    $user->save();

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('password and remember_token are hidden from array and json representations', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');

    $json = json_decode($user->toJson(), true);

    expect($json)->not->toHaveKey('password');
    expect($json)->not->toHaveKey('remember_token');
});
