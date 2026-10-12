<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateOfferProductsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000007_create_offer_products_table.php');
}

test('the migration leaves an existing offer_products table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('offer_products');
    Schema::create('offer_products', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateOfferProductsTableMigration()->up();

    expect(Schema::hasColumn('offer_products', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('offer_products', 'offer_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('offer_products', [
        'id', 'offer_id', 'product_id', 'is_base_product', 'quantity', 'discount', 'discount_type', 'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a offer_products row gets the legacy defaults', function () {
    DB::table('offer_products')->insert(['offer_id' => 1, 'product_id' => 1, 'is_base_product' => true]);

    expect((array) DB::table('offer_products')->first(['quantity', 'discount']))->toEqual([
        'quantity' => 0,
        'discount' => 0,
    ]);
});

test('the migration skips dropping the offer_products table in production', function () {
    app()->instance('env', 'production');
    loadCreateOfferProductsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('offer_products'))->toBeTrue();
});
