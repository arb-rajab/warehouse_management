<?php

use App\Models\Cell;
use App\Models\Row;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('resizing a row regenerates its cells for the new dimensions, leaving other rows cells alone', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 2]);
    $noiseRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 3]);
    $noiseCellIds = $noiseRow->cells()->orderBy('id')->pluck('id')->all();

    $row->update(['cells_count' => 3, 'flats_count' => 1]);

    $coordinates = $row->cells()
        ->orderBy('cell_number')
        ->get(['cell_number', 'flat_number'])
        ->map(fn (Cell $cell): array => ['cell_number' => $cell->cell_number, 'flat_number' => $cell->flat_number])
        ->all();

    expect($coordinates)->toBe([
        ['cell_number' => 1, 'flat_number' => 1],
        ['cell_number' => 2, 'flat_number' => 1],
        ['cell_number' => 3, 'flat_number' => 1],
    ])->and($noiseRow->cells()->orderBy('id')->pluck('id')->all())->toBe($noiseCellIds);
});

test('a failed cell regeneration rolls back the delete, so the row keeps its previous cells', function () {
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $originalCellIds = $row->cells()->orderBy('id')->pluck('id')->all();

    // Make the regeneration's bulk insert fail after the old cells were deleted.
    DB::statement("CREATE TRIGGER fail_cell_insert BEFORE INSERT ON cells BEGIN SELECT RAISE(ABORT, 'cell insert failed'); END");

    expect(fn () => $row->update(['cells_count' => 4]))->toThrow(QueryException::class);

    expect(Cell::query()->where('row_id', $row->id)->orderBy('id')->pluck('id')->all())->toBe($originalCellIds);
});
