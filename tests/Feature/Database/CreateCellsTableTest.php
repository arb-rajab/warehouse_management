<?php

use App\Models\Cell;
use App\Models\Row;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a cell coordinate must be unique within its row', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $existingCell = $row->cells()->first();

    expect(fn () => Cell::factory()->create([
        'row_id' => $row->id,
        'cell_number' => $existingCell->cell_number,
        'flat_number' => $existingCell->flat_number,
    ]))->toThrow(QueryException::class);
});

test('deleting a row cascades to delete its cells', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $cellIds = $row->cells()->pluck('id');

    $row->delete();

    expect(Cell::whereIn('id', $cellIds)->count())->toBe(0);
    expect($otherRow->cells()->count())->toBe(2);
});

test('the cells table has an is_active column defaulting to true', function () {
    expect(Schema::hasColumn('cells', 'is_active'))->toBeTrue();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    expect($cell->is_active)->toBeTrue();
});
