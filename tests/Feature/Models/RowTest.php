<?php

use App\Enums\CellState;
use App\Models\Cell;
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
