<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateTrucksTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000014_create_trucks_table.php');
}

test('the migration leaves an existing trucks table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('trucks');
    Schema::create('trucks', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateTrucksTableMigration()->up();

    expect(Schema::hasColumn('trucks', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('trucks', 'name'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('trucks', [
        'id', 'name', 'model', 'max_pallets_number', 'description', 'license_plate', 'photos', 'is_available',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a trucks row gets the legacy defaults', function () {
    DB::table('trucks')->insert(['name' => 'Truck 1']);

    expect((array) DB::table('trucks')->first(['is_available']))->toEqual([
        'is_available' => 1,
    ]);
});

test('the migration skips dropping the trucks table in production', function () {
    app()->instance('env', 'production');
    loadCreateTrucksTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('trucks'))->toBeTrue();
});
