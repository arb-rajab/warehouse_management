<?php

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('rapid_actions flags the action once the same user exceeds the configured threshold within the window', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    $user = User::factory()->create();

    // Exactly the configured threshold (10) — not yet "more than" it.
    for ($i = 0; $i < 10; $i++) {
        Carbon::setTestNow(now()->addSeconds(10));
        CellStatusLog::factory()->create(['user_id' => $user->id]);
    }

    $tenth = CellStatusLog::query()->where('user_id', $user->id)->latest('id')->first();
    expect($tenth->flags()->where('reason', CellLogFlagReason::RapidActions)->exists())->toBeFalse();

    // The 11th action within the same rolling window exceeds the threshold.
    Carbon::setTestNow(now()->addSeconds(10));
    $eleventh = CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($eleventh->flags()->where('reason', CellLogFlagReason::RapidActions)->exists())->toBeTrue();
});

test('rapid_actions does not count actions by a different user towards the same threshold', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        Carbon::setTestNow(now()->addSeconds(10));
        CellStatusLog::factory()->create(['user_id' => $otherUser->id]);
    }

    Carbon::setTestNow(now()->addSeconds(10));
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($log->flags()->where('reason', CellLogFlagReason::RapidActions)->exists())->toBeFalse();
});

test('rapid_actions does not count an action outside the rolling window', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        CellStatusLog::factory()->create(['user_id' => $user->id]);
    }

    // Past the configured 5-minute window, so the 10 earlier actions no longer count.
    Carbon::setTestNow('2026-08-01 10:10:00');
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($log->flags()->where('reason', CellLogFlagReason::RapidActions)->exists())->toBeFalse();
});

test('off_hours flags an action just outside the configured working-hours window', function () {
    Carbon::setTestNow('2026-08-01 05:59:00');

    $log = CellStatusLog::factory()->create();

    expect($log->flags()->where('reason', CellLogFlagReason::OffHours)->exists())->toBeTrue();
});

test('off_hours does not flag an action right at the start of the configured working-hours window', function () {
    Carbon::setTestNow('2026-08-01 06:00:00');

    $log = CellStatusLog::factory()->create();

    expect($log->flags()->where('reason', CellLogFlagReason::OffHours)->exists())->toBeFalse();
});

test('off_hours does not flag an action within the configured working-hours window', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');

    $log = CellStatusLog::factory()->create();

    expect($log->flags()->where('reason', CellLogFlagReason::OffHours)->exists())->toBeFalse();
});

test('off_hours flags an action at or after the end of the configured working-hours window', function () {
    Carbon::setTestNow('2026-08-01 22:00:00');

    $log = CellStatusLog::factory()->create();

    expect($log->flags()->where('reason', CellLogFlagReason::OffHours)->exists())->toBeTrue();
});

test('quick_flip flags an emptied action when the same user stored a pallet in the same cell shortly beforehand', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    $user = User::factory()->create();
    $cell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 12:01:30');
    $emptied = CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied,
    ]);

    expect($emptied->flags()->where('reason', CellLogFlagReason::QuickFlip)->exists())->toBeTrue();
});

test('quick_flip does not fire once the store and empty are further apart than the configured window', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    $user = User::factory()->create();
    $cell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 12:03:00');
    $emptied = CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied,
    ]);

    expect($emptied->flags()->where('reason', CellLogFlagReason::QuickFlip)->exists())->toBeFalse();
});

test('quick_flip does not fire when the store was in a different cell', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    $user = User::factory()->create();
    $storedCell = Cell::factory()->create();
    $emptiedCell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $storedCell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 12:00:30');
    $emptied = CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $emptiedCell->id,
        'action' => CellLogAction::Emptied,
    ]);

    expect($emptied->flags()->where('reason', CellLogFlagReason::QuickFlip)->exists())->toBeFalse();
});

test('quick_flip does not fire when a different user stored the pallet', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    $storingUser = User::factory()->create();
    $emptyingUser = User::factory()->create();
    $cell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $storingUser->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 12:00:30');
    $emptied = CellStatusLog::factory()->create([
        'user_id' => $emptyingUser->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied,
    ]);

    expect($emptied->flags()->where('reason', CellLogFlagReason::QuickFlip)->exists())->toBeFalse();
});

test('quick_flip does not fire for a non-emptied action, even with a recent store in the same cell', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');
    $user = User::factory()->create();
    $cell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 12:00:30');
    $opened = CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Opened,
    ]);

    expect($opened->flags()->where('reason', CellLogFlagReason::QuickFlip)->exists())->toBeFalse();
});

test('a single log entry can accumulate multiple flags at once', function () {
    Carbon::setTestNow('2026-08-01 23:00:00');
    $user = User::factory()->create();
    $cell = Cell::factory()->create();

    CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-01 23:00:30');
    $emptied = CellStatusLog::factory()->create([
        'user_id' => $user->id,
        'cell_id' => $cell->id,
        'action' => CellLogAction::Emptied,
    ]);

    expect($emptied->flags()->orderBy('id')->pluck('reason')->all())->toEqual([
        CellLogFlagReason::OffHours,
        CellLogFlagReason::QuickFlip,
    ]);
});
