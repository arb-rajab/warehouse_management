<?php

use App\Enums\CellLogAction;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddPalletCorrectionsToCellStatusLogsTableMigration(): object
{
    return require database_path('migrations/2026_11_12_100002_add_pallet_corrections_to_cell_status_logs_table.php');
}

/**
 * @return array<string, array<string, mixed>>
 */
function cellStatusLogsColumns(): array
{
    return collect(Schema::getColumns('cell_status_logs'))->keyBy('name')->all();
}

test('cell_id and both states are nullable, and the correction columns exist and are nullable', function () {
    $columns = cellStatusLogsColumns();

    foreach (['cell_id', 'from_state', 'to_state', 'batch_id', 'reason', 'old_product_id', 'old_note', 'new_note'] as $name) {
        expect($columns)->toHaveKey($name)
            ->and($columns[$name]['nullable'])->toBeTrue();
    }

    expect(collect(Schema::getIndexes('cell_status_logs'))->contains(fn (array $index) => $index['columns'] === ['batch_id']))->toBeTrue();
});

test('a log without a cell or states can be written', function () {
    $log = CellStatusLog::factory()->create(['cell_id' => null, 'from_state' => null, 'to_state' => null, 'action' => CellLogAction::NoteChanged]);

    expect(DB::table('cell_status_logs')->where('id', $log->id)->value('cell_id'))->toBeNull();
});

test('the foreign key to cells still restricts deleting a logged cell and refuses an unknown cell', function () {
    $log = CellStatusLog::factory()->create();
    $unlogged = Cell::factory()->create(); // noise

    expect(fn () => Cell::query()->whereKey($log->cell_id)->delete())->toThrow(QueryException::class)
        ->and(Cell::query()->whereKey($log->cell_id)->exists())->toBeTrue()
        ->and(fn () => CellStatusLog::factory()->create(['cell_id' => 999_999]))->toThrow(QueryException::class);

    $unlogged->delete();
    expect(Cell::query()->whereKey($unlogged->id)->exists())->toBeFalse();
});

test('deleting the previous product nulls old_product_id and keeps the log', function () {
    $old = Product::factory()->create();
    $kept = Product::factory()->create();
    $log = CellStatusLog::factory()->create(['action' => CellLogAction::ProductChanged, 'old_product_id' => $old->id]);
    $other = CellStatusLog::factory()->create(['action' => CellLogAction::ProductChanged, 'old_product_id' => $kept->id]); // noise

    DB::table('products')->where('id', $old->id)->delete();

    expect($log->fresh()->old_product_id)->toBeNull()
        ->and($other->fresh()->old_product_id)->toBe($kept->id);
});

test('old_product_id refuses a product that does not exist', function () {
    expect(fn () => CellStatusLog::factory()->create(['old_product_id' => 999_999]))->toThrow(QueryException::class);
});

test('down() refuses to restore NOT NULL while a log without a cell exists', function () {
    CellStatusLog::factory()->create(['cell_id' => null, 'from_state' => null, 'to_state' => null, 'action' => CellLogAction::NoteChanged]);

    expect(fn () => loadAddPalletCorrectionsToCellStatusLogsTableMigration()->down())
        ->toThrow(RuntimeException::class, 'logs without a cell exist');

    expect(cellStatusLogsColumns()['cell_id']['nullable'])->toBeTrue()
        ->and(Schema::hasColumn('cell_status_logs', 'batch_id'))->toBeTrue();
});

test('down() restores NOT NULL and drops the correction columns once every log has a cell, and up() redoes it', function () {
    $migration = loadAddPalletCorrectionsToCellStatusLogsTableMigration();
    $log = CellStatusLog::factory()->create();

    $migration->down();

    try {
        $columns = cellStatusLogsColumns();
        expect($columns['cell_id']['nullable'])->toBeFalse()
            ->and($columns['from_state']['nullable'])->toBeFalse()
            ->and($columns['to_state']['nullable'])->toBeFalse()
            ->and($columns)->not->toHaveKeys(['batch_id', 'reason', 'old_product_id', 'old_note', 'new_note'])
            ->and(DB::table('cell_status_logs')->where('id', $log->id)->value('cell_id'))->toBe($log->cell_id);
    } finally {
        $migration->up();
    }

    expect(cellStatusLogsColumns()['cell_id']['nullable'])->toBeTrue()
        ->and(Schema::hasColumn('cell_status_logs', 'old_product_id'))->toBeTrue();
});
