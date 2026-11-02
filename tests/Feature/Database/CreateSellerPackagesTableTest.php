<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('seller_packages has the expected columns and defaults', function () {
    expect(Schema::hasTable('seller_packages'))->toBeTrue();
    expect(Schema::hasColumns('seller_packages', ['id', 'name', 'amount', 'product_upload_limit', 'duration', 'logo', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('seller_packages')->insert(['name' => 'Gold']);

    $this->assertDatabaseHas('seller_packages', [...['name' => 'Gold'], ...['amount' => 0.00, 'product_upload_limit' => 0, 'duration' => 0, 'logo' => null]]);
});

test('the seller_packages migration leaves an existing table and its rows alone', function () {
    DB::table('seller_packages')->insert(['name' => 'Gold']);

    $migration = require database_path('migrations/2026_10_31_100600_create_seller_packages_table.php');
    $migration->up();

    expect(Schema::hasTable('seller_packages'))->toBeTrue();
    expect(DB::table('seller_packages')->count())->toBe(1);
});
