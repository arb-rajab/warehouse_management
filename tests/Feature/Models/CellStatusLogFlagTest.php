<?php

use App\Enums\CellLogFlagReason;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\User;
use Carbon\CarbonInterface;

test('a cell status log flag belongs to its cell status log', function () {
    $log = CellStatusLog::factory()->create();
    $otherLog = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    expect($flag->cellStatusLog->id)->toBe($log->id);
    expect($flag->cellStatusLog->id)->not->toBe($otherLog->id);
});

test('a cell status log flag has no acknowledging user until acknowledged', function () {
    $flag = CellStatusLogFlag::factory()->create();

    expect($flag->acknowledgedBy)->toBeNull();
});

test('a cell status log flag resolves the user who acknowledged it', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $flag = CellStatusLogFlag::factory()->create([
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    expect($flag->acknowledgedBy->id)->toBe($user->id);
    expect($flag->acknowledgedBy->id)->not->toBe($otherUser->id);
});

test('the reason attribute is cast to a CellLogFlagReason enum', function () {
    $flag = CellStatusLogFlag::factory()->create(['reason' => CellLogFlagReason::OffHours]);

    expect($flag->fresh()->reason)->toBe(CellLogFlagReason::OffHours);
});

test('the acknowledged_at attribute is cast to a date, when present', function () {
    $unacknowledged = CellStatusLogFlag::factory()->create();
    expect($unacknowledged->fresh()->acknowledged_at)->toBeNull();

    $acknowledged = CellStatusLogFlag::factory()->create(['acknowledged_at' => '2026-08-20 09:00:00']);
    expect($acknowledged->fresh()->acknowledged_at)->toBeInstanceOf(CarbonInterface::class);
});
