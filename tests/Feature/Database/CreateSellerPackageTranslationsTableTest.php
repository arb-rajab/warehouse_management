<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * This table is the store's own, carried over for the code that still reads it.
 * No earlier migration created it, so a database built from migrations alone
 * failed with SQLSTATE[42S02] where the code reached it.
 */
test('seller_package_translations has the expected columns and defaults', function () {
    expect(Schema::hasTable('seller_package_translations'))->toBeTrue();
    expect(Schema::hasColumns('seller_package_translations', ['id', 'seller_package_id', 'name', 'lang', 'created_at', 'updated_at']))->toBeTrue();

    DB::table('seller_package_translations')->insert(['seller_package_id' => 1, 'name' => 'Gold', 'lang' => 'sa']);

    $this->assertDatabaseHas('seller_package_translations', [...['seller_package_id' => 1, 'name' => 'Gold', 'lang' => 'sa'], ...[]]);
});

test('the seller_package_translations migration leaves an existing table and its rows alone', function () {
    DB::table('seller_package_translations')->insert(['seller_package_id' => 1, 'name' => 'Gold', 'lang' => 'sa']);

    $migration = require database_path('migrations/2026_10_31_100700_create_seller_package_translations_table.php');
    $migration->up();

    expect(Schema::hasTable('seller_package_translations'))->toBeTrue();
    expect(DB::table('seller_package_translations')->count())->toBe(1);
});
