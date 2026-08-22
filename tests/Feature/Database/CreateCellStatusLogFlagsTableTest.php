<?php

use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\User;

test('deleting a cell status log cascades to delete its flags, leaving another logs flags untouched', function () {
    $log = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $otherLog = CellStatusLog::factory()->create();
    $otherFlag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $otherLog->id]);

    $log->delete();

    expect(CellStatusLogFlag::find($flag->id))->toBeNull();
    expect(CellStatusLogFlag::find($otherFlag->id))->not->toBeNull();
});

test('deleting the user who acknowledged a flag nulls its acknowledged_by, leaving the flag itself intact', function () {
    $user = User::factory()->create();
    $flag = CellStatusLogFlag::factory()->create([
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    $user->delete();

    $fresh = $flag->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->acknowledged_by)->toBeNull();
    expect($fresh->acknowledged_at)->not->toBeNull();
});
