<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a barcode must be unique across every product, not just within one', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1234567890123']);

    expect(fn () => ProductBarcode::factory()->create(['product_id' => $otherProduct->id, 'barcode' => '1234567890123']))
        ->toThrow(QueryException::class);
});

test('deleting a product cascades to its barcodes and leaves other products\' barcodes alone', function () {
    // Pallets restrict a product delete, so this product deliberately has none.
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $survivor = Product::factory()->create();
    $survivorBarcode = ProductBarcode::factory()->create(['product_id' => $survivor->id]);

    $product->delete();

    expect(ProductBarcode::query()->whereKey($barcode->id)->exists())->toBeFalse();
    expect(ProductBarcode::query()->whereKey($survivorBarcode->id)->exists())->toBeTrue();
});

test('barcodes are wms-owned rows rather than the shared products table\'s own barcode column', function () {
    // `products.barcode` is the store's single barcode per product, which its
    // seller pages still read and write; this app's barcodes never touch it.
    expect(Schema::hasTable('wms_product_barcodes'))->toBeTrue()
        ->and((new Product)->barcodes()->getRelated()->getTable())->toBe('wms_product_barcodes')
        ->and((new Product)->getFillable())->not->toContain('barcode');
});
