<?php

use App\Models\Product;
use App\Models\StoreProductBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateProductBarcodesTableMigration(): object
{
    return require database_path('migrations/2026_09_29_000002_create_product_barcodes_table.php');
}

test('the migration leaves an existing product_barcodes table alone', function () {
    // The shared-database case: `product_barcodes` belongs to the store app,
    // with columns this app never reads.
    Schema::dropIfExists('product_barcodes');
    Schema::create('product_barcodes', function (Blueprint $table) {
        $table->id();
        $table->string('store_only_column')->nullable();
    });

    loadCreateProductBarcodesTableMigration()->up();

    expect(Schema::hasColumn('product_barcodes', 'store_only_column'))->toBeTrue();
    expect(Schema::hasColumn('product_barcodes', 'barcode'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('product_barcodes', ['id', 'product_id', 'barcode', 'created_at', 'updated_at']))->toBeTrue();
});

test('deleting a product with barcodes is restricted, and the product survives', function () {
    $product = Product::create(['name' => 'Widget', 'ar_name' => 'ويدجت']);
    StoreProductBarcode::create(['product_id' => $product->id, 'barcode' => '1234567890']);

    expect(fn () => $product->delete())->toThrow(QueryException::class);
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

test('the migration skips dropping the product_barcodes table in production', function () {
    Schema::dropIfExists('product_barcodes');
    loadCreateProductBarcodesTableMigration()->up();

    app()->instance('env', 'production');
    loadCreateProductBarcodesTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('product_barcodes'))->toBeTrue();
});
