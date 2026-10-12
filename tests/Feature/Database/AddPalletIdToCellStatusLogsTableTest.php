<?php

use App\Models\CellStatusLog;
use App\Models\Pallet;
use Illuminate\Support\Facades\Schema;

function loadAddPalletIdToCellStatusLogsTableMigration(): object
{
    return require database_path('migrations/2026_08_11_000000_add_pallet_id_to_cell_status_logs_table.php');
}

test('cell_status_logs.pallet_id has an index', function () {
    $indexes = collect(Schema::getIndexes('cell_status_logs'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['pallet_id']))->toBeTrue();
});

test('deleting a pallet referenced by a cell status log does not throw and the raw column survives', function () {
    // No FK constraint by design: `empty` hard-deletes the pallet, and any
    // onDelete rule — even nullOnDelete — would wipe this id from every
    // earlier log row for that pallet the moment it's emptied. Deleting the
    // pallet must therefore succeed, and the raw pallet_id value must remain
    // on the log row even though the Eloquent relation now resolves null.
    $pallet = Pallet::factory()->create();
    $log = CellStatusLog::factory()->create(['pallet_id' => $pallet->id]);

    $pallet->delete();

    $fresh = $log->fresh();
    expect($fresh->pallet_id)->toBe($pallet->id);
    expect($fresh->pallet)->toBeNull();
});

test('the migration is reversible', function () {
    $migration = loadAddPalletIdToCellStatusLogsTableMigration();

    $migration->down();
    expect(Schema::hasColumn('cell_status_logs', 'pallet_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('cell_status_logs', 'pallet_id'))->toBeTrue();
    expect(collect(Schema::getIndexes('cell_status_logs'))->contains(fn (array $index) => $index['columns'] === ['pallet_id']))->toBeTrue();
});
