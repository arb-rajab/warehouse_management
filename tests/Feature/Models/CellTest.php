<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Row;

test('a cell belongs to its row', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $cell = $row->cells()->first();

    expect($cell->row->id)->toBe($row->id);
    expect($cell->row->id)->not->toBe($otherRow->id);
});

test('a cell with a pallet resolves it through the pallet relation', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);
    $otherPallet = Pallet::factory()->create(['cell_id' => $otherCell->id]);

    expect($cell->pallet->id)->toBe($pallet->id);
    expect($cell->pallet->id)->not->toBe($otherPallet->id);
});

test('a cell without a pallet resolves the pallet relation as null', function () {
    $cell = Cell::factory()->create();

    expect($cell->pallet)->toBeNull();
});

test('the state attribute is cast to a CellState enum', function () {
    $cell = Cell::factory()->create(['state' => CellState::Opened]);

    expect($cell->fresh()->state)->toBe(CellState::Opened);
});

test('a cell defaults to active', function () {
    $cell = Cell::factory()->create();

    expect($cell->fresh()->is_active)->toBeTrue();
});

test('the is_active attribute is cast to a boolean', function () {
    $cell = Cell::factory()->inactive()->create();

    expect($cell->fresh()->is_active)->toBeFalse();
});

test('toLocationArray describes the cell by its row letter, cell number, and flat number', function () {
    $row = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]);

    $cell = $row->cells()->first();

    expect($cell->toLocationArray())->toBe([
        'row_letter' => 'B',
        'cell_number' => $cell->cell_number,
        'flat_number' => $cell->flat_number,
    ]);
    expect($cell->toLocationArray()['row_letter'])->not->toBe($otherRow->letter);
});

test('slotLabel formats the row letter, cell number, and flat number as the printed QR label', function () {
    expect(Cell::slotLabel('A', 1, 2))->toBe('A1·2');
    expect(Cell::slotLabel('BC', 12, 3))->toBe('BC12·3');
});

test('the atCoordinates scope finds the exact matching cell', function () {
    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 2]);

    $cell = Cell::query()->atCoordinates($row, 2, 1)->first();

    expect($cell)->not->toBeNull();
    expect($cell->cell_number)->toBe(2);
    expect($cell->flat_number)->toBe(1);
    expect($cell->row_id)->toBe($row->id);
});

test('the atCoordinates scope excludes a cell with the same coordinates in a different row', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);

    $cell = Cell::query()->atCoordinates($row, 1, 1)->first();

    expect($cell->row_id)->toBe($row->id);
    expect($cell->row_id)->not->toBe($otherRow->id);
});

test('the atCoordinates scope excludes a different cell in the same row', function () {
    $row = Row::factory()->create(['cells_count' => 3, 'flats_count' => 2]);
    $otherCellInSameRow = $row->cells()->where('cell_number', 2)->where('flat_number', 1)->first();

    $cell = Cell::query()->atCoordinates($row, 1, 1)->first();

    expect($cell->id)->not->toBe($otherCellInSameRow->id);
});

test('the atCoordinates scope excludes a cell with the same row and cell number but a different flat number', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $otherFlatInSameCell = $row->cells()->where('cell_number', 1)->where('flat_number', 2)->first();

    $cell = Cell::query()->atCoordinates($row, 1, 1)->first();

    expect($cell->id)->not->toBe($otherFlatInSameCell->id);
});

test('the orderedByCoordinates scope orders cells by cell number then flat number regardless of insertion order', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $row->cells()->delete();

    $now = now();
    Cell::insert([
        ['row_id' => $row->id, 'cell_number' => 2, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
        ['row_id' => $row->id, 'cell_number' => 1, 'flat_number' => 2, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
        ['row_id' => $row->id, 'cell_number' => 1, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $cells = $row->cells()->orderedByCoordinates()->get();

    expect($cells->map(fn (Cell $cell) => [$cell->cell_number, $cell->flat_number])->all())
        ->toBe([[1, 1], [1, 2], [2, 1]]);
});
