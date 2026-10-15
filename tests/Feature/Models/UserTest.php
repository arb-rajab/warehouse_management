<?php

use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
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

test('filterOptions includes users who have been deleted, so their history stays filterable', function () {
    $active = User::factory()->create(['name' => 'Alpha']);
    $removed = User::factory()->mobileUser()->create(['name' => 'Bravo']);
    $removed->delete();

    expect(User::filterOptions()->pluck('id')->all())->toBe([$active->id, $removed->id]);
});

test('a deleted user is soft deleted: skipped by default queries but still stored', function () {
    $user = User::factory()->mobileUser()->create();
    $other = User::factory()->mobileUser()->create();
    CellStatusLog::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(User::find($user->id))->toBeNull();
    expect(User::find($other->id))->not->toBeNull();
    expect(User::withTrashed()->find($user->id)->deleted_at)->toBeInstanceOf(CarbonImmutable::class);
    expect(CellStatusLog::where('user_id', $user->id)->exists())->toBeTrue();
});

test('every relation to a user still resolves them after they are deleted', function () {
    $user = User::factory()->create(['name' => 'Bob Mover']);
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);
    $round = CellVerificationRound::factory()->create(['user_id' => $user->id]);
    $report = CellVerificationReport::factory()->accepted()->create(['user_id' => $user->id, 'accepted_by' => $user->id]);
    $flag = CellStatusLogFlag::factory()->create(['acknowledged_at' => now(), 'acknowledged_by' => $user->id]);

    $user->delete();

    expect($log->fresh()->user->name)->toBe('Bob Mover');
    expect($round->fresh()->user->name)->toBe('Bob Mover');
    expect($report->fresh()->user->name)->toBe('Bob Mover');
    expect($report->fresh()->acceptedBy->name)->toBe('Bob Mover');
    expect($flag->fresh()->acknowledgedBy->name)->toBe('Bob Mover');
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
