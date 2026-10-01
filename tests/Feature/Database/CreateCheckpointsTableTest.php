<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateCheckpointsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000016_create_checkpoints_table.php');
}

test('the migration leaves an existing checkpoints table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('checkpoints');
    Schema::create('checkpoints', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateCheckpointsTableMigration()->up();

    expect(Schema::hasColumn('checkpoints', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('checkpoints', 'trip_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('checkpoints', [
        'id', 'trip_id', 'relationable_type', 'relationable_id', 'type', 'status', 'arrived_at', 'address',
        'longitudes', 'latitudes', 'sort_order_in_trip', 'eta', 'notes', 'cmr_file', 'photos', 'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a checkpoints row gets the legacy defaults', function () {
    DB::table('checkpoints')->insert(['trip_id' => 1, 'relationable_type' => 'order', 'relationable_id' => 1, 'sort_order_in_trip' => 0]);

    expect((array) DB::table('checkpoints')->first(['type', 'status']))->toEqual([
        'type' => 'delivery',
        'status' => 'pending',
    ]);
});

test('a checkpoint is a delivery or other stop, and pending, delivered or has_issue', function () {
    $checkpoint = ['trip_id' => 1, 'relationable_type' => 'order', 'relationable_id' => 1, 'sort_order_in_trip' => 0];

    expect(fn () => DB::table('checkpoints')->insert([...$checkpoint, 'type' => 'pickup']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('checkpoints')->insert([...$checkpoint, 'status' => 'lost']))->toThrow(QueryException::class);
});

test('the migration skips dropping the checkpoints table in production', function () {
    app()->instance('env', 'production');
    loadCreateCheckpointsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('checkpoints'))->toBeTrue();
});
