<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('seller_package_payments has the expected columns and defaults', function () {
    expect(Schema::hasTable('seller_package_payments'))->toBeTrue();
    expect(Schema::hasColumns('seller_package_payments', ['id', 'user_id', 'seller_package_id', 'payment_method', 'payment_details', 'approval', 'offline_payment', 'reciept', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('seller_package_payments')->insert(['user_id' => 1, 'seller_package_id' => 2]);

    $this->assertDatabaseHas('seller_package_payments', [...['user_id' => 1, 'seller_package_id' => 2], ...['payment_method' => null, 'payment_details' => null, 'approval' => 0, 'offline_payment' => 0, 'reciept' => null]]);
});

test('the seller_package_payments migration leaves an existing table and its rows alone', function () {
    DB::table('seller_package_payments')->insert(['user_id' => 1, 'seller_package_id' => 2]);

    $migration = require database_path('migrations/2026_10_31_100800_create_seller_package_payments_table.php');
    $migration->up();

    expect(Schema::hasTable('seller_package_payments'))->toBeTrue();
    expect(DB::table('seller_package_payments')->count())->toBe(1);
});
