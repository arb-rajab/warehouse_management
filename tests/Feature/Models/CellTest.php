<?php

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Row;
use Illuminate\Http\Request;

test('a cell belongs to its row', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $cell = $row->cells()->first();

    expect($cell->row->id)->toBe($row->id);
    expect($cell->row->id)->not->toBe($otherRow->id);
});

test('a cell with a pallet resolves it through the pallet relation', function () {
    $pallet = Pallet::factory()->create();
    $emptyCell = Cell::factory()->create();

    $cell = $pallet->cell;

    expect($cell->pallet)->not->toBeNull();
    expect($cell->pallet->id)->toBe($pallet->id);
    expect($emptyCell->pallet)->toBeNull();
});

test('the state attribute is cast to a CellState enum', function () {
    $cell = Cell::factory()->create(['state' => CellState::Opened]);

    expect($cell->fresh()->state)->toBe(CellState::Opened);
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

test('the filtered scope filters by state, excluding cells in other states', function () {
    $matching = Cell::factory()->create(['state' => CellState::Opened]);
    Cell::factory()->create(['state' => CellState::Empty]);

    $cells = Cell::query()->filtered(new Request(['state' => 'opened']))->get();

    expect($cells->pluck('id')->all())->toBe([$matching->id]);
});

test('the filtered scope filters by row_id, excluding cells in other rows', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $matching = $row->cells()->first();
    $otherRow->cells()->first();

    $cells = Cell::query()->filtered(new Request(['row_id' => $row->id]))->get();

    expect($cells->pluck('id')->all())->toBe([$matching->id]);
});

test('the filtered scope filters by column_number, excluding cells in other columns', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $matching = $row->cells()->where('cell_number', 1)->first();
    $row->cells()->where('cell_number', 2)->first();

    $cells = Cell::query()->filtered(new Request(['column_number' => 1]))->get();

    expect($cells->pluck('id')->all())->toBe([$matching->id]);
});

test('the filtered scope filters by pallet expiration date range, excluding out-of-range pallets and cells with no pallet', function () {
    $matchingPallet = Pallet::factory()->create(['expiration_date' => '2026-06-15']);
    $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-01-01']);
    Cell::factory()->create();

    $cells = Cell::query()->filtered(new Request([
        'expiration_date_from' => '2026-06-01',
        'expiration_date_to' => '2026-06-30',
    ]))->get();

    expect($cells->pluck('id')->all())->toBe([$matchingPallet->cell_id])
        ->and($cells->pluck('id')->all())->not->toContain($outOfRangePallet->cell_id);
});

test('the sorted scope orders by pallet expiration date when sort_by is expiration_date', function () {
    $soonPallet = Pallet::factory()->create(['expiration_date' => '2026-06-01']);
    $latePallet = Pallet::factory()->create(['expiration_date' => '2026-12-01']);

    $ascending = Cell::query()->sorted(new Request(['sort_by' => 'expiration_date', 'sort_direction' => 'asc']))->get();

    expect($ascending->pluck('id')->all())->toBe([$soonPallet->cell_id, $latePallet->cell_id]);

    $descending = Cell::query()->sorted(new Request(['sort_by' => 'expiration_date', 'sort_direction' => 'desc']))->get();

    expect($descending->pluck('id')->all())->toBe([$latePallet->cell_id, $soonPallet->cell_id]);
});

test('the sorted scope falls back to orderedByCoordinates when no sort_by is given', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $row->cells()->delete();

    $now = now();
    Cell::insert([
        ['row_id' => $row->id, 'cell_number' => 2, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
        ['row_id' => $row->id, 'cell_number' => 1, 'flat_number' => 1, 'state' => CellState::Empty->value, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $cells = $row->cells()->sorted(new Request)->get();

    expect($cells->map(fn (Cell $cell) => $cell->cell_number)->all())->toBe([1, 2]);
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
