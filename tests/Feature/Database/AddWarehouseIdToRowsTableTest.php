<?php

use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `add_warehouse_id_to_rows_table`: every existing row is moved into a
 * "Default" warehouse the migration creates (or reuses), `warehouse_id` is
 * NOT NULL and restricts deleting its warehouse, and a letter is unique per
 * warehouse instead of across the WMS.
 *
 * The rollback tests insert rows with DB::table() and no cells: on sqlite the
 * foreign-key change rebuilds `rows`, and inside the test's transaction that
 * rebuild cannot switch foreign keys off, so a row with cells would take them
 * with it.
 */
function loadAddWarehouseIdToRowsMigration(): object
{
    return require database_path('migrations/2026_11_08_120001_add_warehouse_id_to_rows_table.php');
}

function insertBareRow(string $letter, array $extra = []): int
{
    return DB::table('rows')->insertGetId([
        'letter' => $letter,
        'cells_count' => 1,
        'flats_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
        ...$extra,
    ]);
}

test('migrating creates the Default warehouse every new installation starts with', function () {
    expect(Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->count())->toBe(1);
});

test('rows that existed before warehouses are backfilled into the Default warehouse', function () {
    $migration = loadAddWarehouseIdToRowsMigration();
    $defaultId = Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->value('id');

    $migration->down();
    expect(Schema::hasColumn('rows', 'warehouse_id'))->toBeFalse();

    $first = insertBareRow('A');
    $second = insertBareRow('B');

    $migration->up();

    expect(DB::table('rows')->whereIn('id', [$first, $second])->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all())
        ->toBe([$defaultId, $defaultId])
        // The existing Default is reused, not duplicated, on a re-run.
        ->and(Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->count())->toBe(1);
});

test('the backfill creates the Default warehouse when it does not exist yet', function () {
    $migration = loadAddWarehouseIdToRowsMigration();

    $migration->down();
    DB::table('warehouses')->delete();
    $row = insertBareRow('A');

    $migration->up();

    $default = Warehouse::query()->where('name', Warehouse::DEFAULT_NAME)->sole();
    expect((int) DB::table('rows')->where('id', $row)->value('warehouse_id'))->toBe($default->id);
});

test('rows.warehouse_id is NOT NULL', function () {
    $column = collect(Schema::getColumns('rows'))->firstWhere('name', 'warehouse_id');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeFalse();
    expect(fn () => insertBareRow('Q', ['warehouse_id' => null]))->toThrow(QueryException::class);
});

test('a warehouse that still has rows cannot be deleted at the database level', function () {
    $warehouse = Warehouse::factory()->create();
    Row::factory()->for($warehouse)->create(['cells_count' => 1, 'flats_count' => 1]);

    expect(fn () => DB::table('warehouses')->where('id', $warehouse->id)->delete())->toThrow(QueryException::class);
    expect(Warehouse::query()->whereKey($warehouse->id)->exists())->toBeTrue();
});

test('a row letter is unique per warehouse, not across warehouses', function () {
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    Row::factory()->for($south)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    expect(Row::query()->where('letter', 'A')->count())->toBe(2);

    expect(fn () => Row::factory()->for($north)->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]))
        ->toThrow(QueryException::class);
    expect(Row::query()->where('letter', 'A')->count())->toBe(2);
});

test('rolling back drops the column and restores the global unique letter', function () {
    $migration = loadAddWarehouseIdToRowsMigration();

    $migration->down();

    try {
        expect(Schema::hasColumn('rows', 'warehouse_id'))->toBeFalse();
        insertBareRow('A');
        expect(fn () => insertBareRow('A'))->toThrow(QueryException::class);
    } finally {
        DB::table('rows')->delete();
        $migration->up();
    }

    expect(Schema::hasColumn('rows', 'warehouse_id'))->toBeTrue();
});
