<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('delivery_histories has the expected columns and defaults', function () {
    expect(Schema::hasTable('delivery_histories'))->toBeTrue();
    expect(Schema::hasColumns('delivery_histories', ['id', 'delivery_boy_id', 'order_id', 'delivery_status', 'payment_type', 'earning', 'collection', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('delivery_histories')->insert(['delivery_boy_id' => 1, 'order_id' => 2]);

    $this->assertDatabaseHas('delivery_histories', [...['delivery_boy_id' => 1, 'order_id' => 2], ...['delivery_status' => null, 'payment_type' => null, 'earning' => 0.00, 'collection' => 0.00]]);
});

test('the delivery_histories migration leaves an existing table and its rows alone', function () {
    DB::table('delivery_histories')->insert(['delivery_boy_id' => 1, 'order_id' => 2]);

    $migration = require database_path('migrations/2026_10_31_100500_create_delivery_histories_table.php');
    $migration->up();

    expect(Schema::hasTable('delivery_histories'))->toBeTrue();
    expect(DB::table('delivery_histories')->count())->toBe(1);
});
