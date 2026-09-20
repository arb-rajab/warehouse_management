<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Row;

test('a row has many cells, generated on creation for exactly its own dimensions', function () {
    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 2]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $cells = $row->cells;

    expect($cells)->toHaveCount(6);
    expect($cells->pluck('row_id')->unique()->all())->toBe([$row->id]);
    expect($cells->pluck('id'))->not->toContain($otherRow->cells->first()->id);
    expect($otherRow->cells()->count())->toBe(1);

    foreach ([1, 2, 3] as $cellNumber) {
        foreach ([1, 2] as $flatNumber) {
            $cell = $cells->first(fn (Cell $cell) => $cell->cell_number === $cellNumber && $cell->flat_number === $flatNumber);

            expect($cell)->not->toBeNull();
            expect($cell->state)->toBe(CellState::Empty);
        }
    }
});

test('hasPallets is false when none of the rows cells hold a pallet', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    Pallet::factory()->create(['cell_id' => $otherRow->cells()->first()->id]);

    expect($row->hasPallets())->toBeFalse();
});

test('hasPallets is true when one of the rows cells holds a pallet', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $emptyCell = $row->cells()->first();
    $occupiedCell = $row->cells()->skip(1)->first();
    Pallet::factory()->create(['cell_id' => $occupiedCell->id]);

    expect($row->hasPallets())->toBeTrue();
    expect($emptyCell->fresh()->pallet)->toBeNull();
});

test('hasHistory is false when none of the rows cells have a status log or verification report', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    CellStatusLog::factory()->create(['cell_id' => $otherRow->cells()->first()->id]);

    expect($row->hasHistory())->toBeFalse();
});

test('hasHistory is true when one of the rows cells has a status log', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    expect($row->hasHistory())->toBeTrue();
});

test('hasHistory is true when one of the rows cells is only the related_cell_id of a transfer log', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $destinationRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $sourceCell = $row->cells()->first();
    $destinationCell = $destinationRow->cells()->first();
    CellStatusLog::factory()->create([
        'cell_id' => $destinationCell->id,
        'related_cell_id' => $sourceCell->id,
        'action' => CellLogAction::TransferredIn,
    ]);

    expect($row->hasHistory())->toBeTrue();
});

test('hasHistory is true when one of the rows cells has a verification report', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellVerificationReport::factory()->create(['cell_id' => $cell->id]);

    expect($row->hasHistory())->toBeTrue();
});

test('filterOptions returns rows ordered by letter and the highest cells_count as maxColumnNumber', function () {
    $rowC = Row::factory()->create(['letter' => 'C', 'cells_count' => 2, 'flats_count' => 1]);
    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 5, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 3, 'flats_count' => 1]);

    $options = Row::filterOptions();

    expect($options['rows']->pluck('letter')->all())->toBe(['A', 'B', 'C']);
    expect($options['rows']->pluck('id')->all())->toBe([$rowA->id, $rowB->id, $rowC->id]);
    expect($options['maxColumnNumber'])->toBe(5);
});

test('filterOptions maxColumnNumber is 0 when there are no rows', function () {
    $options = Row::filterOptions();

    expect($options['rows'])->toBeEmpty();
    expect($options['maxColumnNumber'])->toBe(0);
});

test('a row has many verification rounds, excluding a round covering another row', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);

    $round = CellVerificationRound::factory()->covering($row)->create();
    CellVerificationRound::factory()->covering($otherRow)->create(); // noise

    expect($row->verificationRounds)->toHaveCount(1);
    expect($row->verificationRounds->first()->id)->toBe($round->id);
});

test('the underActiveVerification scope returns only rows claimed by an unfinished round', function () {
    $claimed = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $released = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]); // noise: never in a round

    CellVerificationRound::factory()->covering($claimed)->create();
    CellVerificationRound::factory()->completed()->covering($released)->create();

    $results = Row::query()->underActiveVerification()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($claimed->id);
});
