<?php

use App\Models\CellVerificationRound;
use App\Models\Row;
use Database\Seeders\CellVerificationRoundSeeder;
use Database\Seeders\RowSeeder;

test('early-returns without a row to seed rounds against', function () {
    (new CellVerificationRoundSeeder)->run();

    expect(CellVerificationRound::count())->toBe(0);
});

test('leaves most of the warehouse actionable after a single run', function () {
    (new RowSeeder)->run();
    (new CellVerificationRoundSeeder)->run();

    $totalRows = Row::count();
    $claimedRows = Row::query()->underActiveVerification()->count();

    expect($totalRows)->toBeGreaterThan(0);
    expect($claimedRows)->toBeLessThan($totalRows / 2);
});

test('running the seeder twice does not create two unfinished rounds over the same row', function () {
    (new RowSeeder)->run();

    (new CellVerificationRoundSeeder)->run();
    (new CellVerificationRoundSeeder)->run();

    $claimedRowIds = CellVerificationRound::query()
        ->whereNull('completed_at')
        ->with('rows:id')
        ->get()
        ->flatMap(fn (CellVerificationRound $round) => $round->rows->pluck('id'));

    expect($claimedRowIds->count())->toBe($claimedRowIds->unique()->count());
});

test('a row already claimed by an unfinished round from a prior run is never claimed again', function () {
    (new RowSeeder)->run();
    (new CellVerificationRoundSeeder)->run();

    $claimedBefore = Row::query()->underActiveVerification()->pluck('id');
    expect($claimedBefore)->not->toBeEmpty();

    (new CellVerificationRoundSeeder)->run();

    // Every row claimed before the second run is still claimed by exactly one
    // unfinished round afterwards — never claimed twice.
    foreach ($claimedBefore as $rowId) {
        $unfinishedRoundsForRow = CellVerificationRound::query()
            ->whereNull('completed_at')
            ->whereHas('rows', fn ($query) => $query->whereKey($rowId))
            ->count();

        expect($unfinishedRoundsForRow)->toBe(1);
    }
});
