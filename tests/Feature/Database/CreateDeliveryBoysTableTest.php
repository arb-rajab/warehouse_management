<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('delivery_boys has the expected columns and defaults', function () {
    expect(Schema::hasTable('delivery_boys'))->toBeTrue();
    expect(Schema::hasColumns('delivery_boys', ['id', 'user_id', 'total_collection', 'total_earning', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('delivery_boys')->insert(['user_id' => 1]);

    $this->assertDatabaseHas('delivery_boys', [...['user_id' => 1], ...['total_collection' => 0.00, 'total_earning' => 0.00]]);
});

test('the delivery_boys migration leaves an existing table and its rows alone', function () {
    DB::table('delivery_boys')->insert(['user_id' => 1]);

    $migration = require database_path('migrations/2026_10_31_100400_create_delivery_boys_table.php');
    $migration->up();

    expect(Schema::hasTable('delivery_boys'))->toBeTrue();
    expect(DB::table('delivery_boys')->count())->toBe(1);
});
