<?php

use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('the users table is renamed to wms_users', function () {
    expect(Schema::hasTable('wms_users'))->toBeTrue();
    expect(Schema::hasTable('users'))->toBeFalse();
});

test('deleting a user referenced by a cell status log is still restricted after the rename', function () {
    $log = CellStatusLog::factory()->create();
    $user = $log->user;

    expect(fn () => $user->delete())->toThrow(QueryException::class);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting the user who acknowledged a flag still nulls acknowledged_by after the rename', function () {
    $user = User::factory()->create();
    $flag = CellStatusLogFlag::factory()->create([
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    $user->delete();

    $fresh = $flag->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->acknowledged_by)->toBeNull();
});
