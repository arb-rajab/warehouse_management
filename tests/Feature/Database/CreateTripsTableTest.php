<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateTripsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000015_create_trips_table.php');
}

test('the migration leaves an existing trips table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('trips');
    Schema::create('trips', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateTripsTableMigration()->up();

    expect(Schema::hasColumn('trips', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('trips', 'code'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('trips', [
        'id', 'code', 'driver_id', 'truck_id', 'due_date', 'active', 'status', 'started_at', 'closed_at', 'notes',
        'distance', 'duration', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a trips row gets the legacy defaults', function () {
    DB::table('trips')->insert(['code' => 'TRIP-1']);

    expect((array) DB::table('trips')->first(['active', 'status']))->toEqual([
        'active' => 0,
        'status' => 'pending',
    ]);
});

test('a trip is pending, started or closed', function () {
    expect(fn () => DB::table('trips')->insert(['code' => 'TRIP-1', 'status' => 'cancelled']))
        ->toThrow(QueryException::class);
});

test('the migration skips dropping the trips table in production', function () {
    app()->instance('env', 'production');
    loadCreateTripsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('trips'))->toBeTrue();
});
