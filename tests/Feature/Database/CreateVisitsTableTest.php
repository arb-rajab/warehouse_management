<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateVisitsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000011_create_visits_table.php');
}

test('the migration leaves an existing visits table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('visits');
    Schema::create('visits', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateVisitsTableMigration()->up();

    expect(Schema::hasColumn('visits', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('visits', 'rep_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('visits', [
        'id', 'rep_id', 'customer_id', 'order_id', 'note', 'map_lat', 'map_lng', 'visit_date', 'rank', 'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a visits row gets the legacy defaults', function () {
    DB::table('visits')->insert(['rep_id' => 1, 'customer_id' => 2]);

    expect((array) DB::table('visits')->first(['rank']))->toEqual([
        'rank' => 0,
    ]);
});

test('the migration skips dropping the visits table in production', function () {
    app()->instance('env', 'production');
    loadCreateVisitsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('visits'))->toBeTrue();
});
