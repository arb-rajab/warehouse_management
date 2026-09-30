<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateProductVariationsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000001_create_product_variations_table.php');
}

test('the migration leaves an existing product_variations table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('product_variations');
    Schema::create('product_variations', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateProductVariationsTableMigration()->up();

    expect(Schema::hasColumn('product_variations', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('product_variations', 'product_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('product_variations', [
        'id', 'product_id', 'name', 'product_serial', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a product with variations cannot be deleted', function () {
    $productId = DB::table('products')->insertGetId(['name' => 'Widget', 'ar_name' => '']);
    DB::table('product_variations')->insert(['product_id' => $productId, 'name' => 'Red']);

    expect(fn () => DB::table('products')->where('id', $productId)->delete())->toThrow(QueryException::class)
        ->and(DB::table('products')->where('id', $productId)->exists())->toBeTrue();
});

test('the migration skips dropping the product_variations table in production', function () {
    app()->instance('env', 'production');
    loadCreateProductVariationsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('product_variations'))->toBeTrue();
});
