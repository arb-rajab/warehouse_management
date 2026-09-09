<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddArNameToProductsStandInMigration(): object
{
    return require database_path('migrations/2026_09_09_000000_add_ar_name_to_products_stand_in.php');
}

/**
 * Rebuilds the pre-`ar_name` stand-in shape. This is what staging and older
 * local checkouts still carry: `create_products_table` was edited to build the
 * column, but a database that already recorded that migration never re-runs it.
 */
function revertProductsToStandInWithoutArName(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn('ar_name');
    });
}

test('the migration leaves a products table that already has ar_name alone', function () {
    // The production case: `products` is the store's own table and already
    // carries `ar_name`. Re-adding it would fail outright, and this app must
    // never alter a table it only reads.
    expect(Schema::hasColumn('products', 'ar_name'))->toBeTrue();

    DB::table('products')->insert([
        'name' => 'Widgets', 'ar_name' => 'ودجات', 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadAddArNameToProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'ar_name'))->toBeTrue();
    expect(DB::table('products')->where('name', 'Widgets')->value('ar_name'))->toBe('ودجات');
});

test('the migration adds ar_name to a stand-in that predates it', function () {
    revertProductsToStandInWithoutArName();
    DB::table('products')->insert([
        'name' => 'Widgets', 'created_at' => now(), 'updated_at' => now(),
    ]);

    loadAddArNameToProductsStandInMigration()->up();

    expect(Schema::hasColumn('products', 'ar_name'))->toBeTrue();
    // NOT NULL upstream, so an existing row takes the empty-string default
    // rather than a null the store's column could never hold.
    expect(DB::table('products')->where('name', 'Widgets')->value('ar_name'))->toBe('');
});

test('the added column stores and returns an Arabic name', function () {
    revertProductsToStandInWithoutArName();
    loadAddArNameToProductsStandInMigration()->up();

    DB::table('products')->insert([
        'name' => 'Widgets', 'ar_name' => 'ودجات كبيرة', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Noise: a second row proving the value is per-row, not shared.
    DB::table('products')->insert([
        'name' => 'Gadgets', 'ar_name' => 'أدوات', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('products')->where('name', 'Widgets')->value('ar_name'))->toBe('ودجات كبيرة');
    expect(DB::table('products')->where('name', 'Gadgets')->value('ar_name'))->toBe('أدوات');
});

test('rolling the migration back drops the column outside production', function () {
    loadAddArNameToProductsStandInMigration()->down();

    expect(Schema::hasColumn('products', 'ar_name'))->toBeFalse();
});

test('the migration skips dropping ar_name from the products table in production', function () {
    app()->instance('env', 'production');
    loadAddArNameToProductsStandInMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasColumn('products', 'ar_name'))->toBeTrue();
});
