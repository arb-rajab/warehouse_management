<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateCartsOfferedTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000005_create_carts_offered_table.php');
}

test('the migration leaves an existing carts_offered table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('carts_offered');
    Schema::create('carts_offered', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateCartsOfferedTableMigration()->up();

    expect(Schema::hasColumn('carts_offered', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('carts_offered', 'owner_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('carts_offered', [
        'id', 'owner_id', 'user_id', 'address_id', 'product_id', 'product_serial', 'variation', 'variation_qty',
        'price', 'tax', 'shipping_cost', 'shipping_type', 'quantity', 'by_rep', 'for_customer', 'item_notes',
        'offer_id', 'is_postponed', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('a carts_offered row gets the legacy defaults', function () {
    DB::table('carts_offered')->insert(['address_id' => 1]);

    expect((array) DB::table('carts_offered')->first(['shipping_cost', 'shipping_type', 'quantity', 'by_rep', 'is_postponed']))->toEqual([
        'shipping_cost' => 0,
        'shipping_type' => 'home_delivery',
        'quantity' => 0,
        'by_rep' => 0,
        'is_postponed' => 0,
    ]);
});

test('the migration skips dropping the carts_offered table in production', function () {
    app()->instance('env', 'production');
    loadCreateCartsOfferedTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('carts_offered'))->toBeTrue();
});
