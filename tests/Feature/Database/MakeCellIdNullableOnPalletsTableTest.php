<?php

use App\Models\Cell;
use App\Models\Pallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadMakeCellIdNullableOnPalletsTableMigration(): object
{
    return require database_path('migrations/2026_11_09_100003_make_cell_id_nullable_on_pallets_table.php');
}

function palletsCellIdColumn(): array
{
    return collect(Schema::getColumns('pallets'))->firstWhere('name', 'cell_id');
}

test('pallets.cell_id is nullable', function () {
    expect(palletsCellIdColumn()['nullable'])->toBeTrue();
});

test('any number of unplaced pallets can coexist under the unique cell index', function () {
    Pallet::factory()->unplaced()->count(3)->create();

    expect(Pallet::query()->whereNull('cell_id')->count())->toBe(3);
});

test('placed pallets still compete for a cell, unplaced ones do not take part', function () {
    $placed = Pallet::factory()->create();
    Pallet::factory()->unplaced()->create(); // noise

    expect(fn () => Pallet::factory()->create(['cell_id' => $placed->cell_id]))->toThrow(QueryException::class);
    expect(Pallet::query()->count())->toBe(2);
});

test('the unique index on cell_id is kept', function () {
    $unique = collect(Schema::getIndexes('pallets'))->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['cell_id']);

    expect($unique)->toBeTrue();
});

test('the foreign key to cells still restricts deleting a cell that holds a pallet', function () {
    $pallet = Pallet::factory()->create();
    $emptyCell = Cell::factory()->create(); // noise

    expect(fn () => $pallet->cell->delete())->toThrow(QueryException::class);
    expect(Cell::query()->whereKey($pallet->cell_id)->exists())->toBeTrue();

    $emptyCell->delete();
    expect(Cell::query()->whereKey($emptyCell->id)->exists())->toBeFalse();
});

test('the foreign key to cells still refuses a pallet in a cell that does not exist', function () {
    expect(fn () => Pallet::factory()->create(['cell_id' => 999_999]))->toThrow(QueryException::class);
});

test('down() refuses to restore NOT NULL while an unplaced pallet exists', function () {
    Pallet::factory()->unplaced()->create();

    expect(fn () => loadMakeCellIdNullableOnPalletsTableMigration()->down())
        ->toThrow(RuntimeException::class, 'unplaced pallets exist');

    // Nothing was changed: the column is still nullable and the pallet still there.
    expect(palletsCellIdColumn()['nullable'])->toBeTrue()
        ->and(Pallet::query()->whereNull('cell_id')->count())->toBe(1);
});

test('down() restores NOT NULL once every pallet is placed, and up() makes it nullable again', function () {
    $migration = loadMakeCellIdNullableOnPalletsTableMigration();
    $placed = Pallet::factory()->create();

    $migration->down();

    try {
        expect(palletsCellIdColumn()['nullable'])->toBeFalse();
        expect(DB::table('pallets')->where('id', $placed->id)->value('cell_id'))->toBe($placed->cell_id);
        expect(fn () => DB::table('pallets')->insert([
            'product_id' => $placed->product_id,
            'cell_id' => null,
            'remaining_boxes' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    } finally {
        $migration->up();
    }

    expect(palletsCellIdColumn()['nullable'])->toBeTrue();
    Pallet::factory()->unplaced()->create();
    expect(Pallet::query()->whereNull('cell_id')->count())->toBe(1);
});
