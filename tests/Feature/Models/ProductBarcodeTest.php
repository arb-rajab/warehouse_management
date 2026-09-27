<?php

use App\Models\Product;
use App\Models\ProductBarcode;

test('a barcode belongs to its product, and not to another one', function () {
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1111111111111']);

    // Noise: another product's barcode must not resolve through this relation.
    $otherProduct = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $otherProduct->id, 'barcode' => '2222222222222']);

    expect($barcode->product->id)->toBe($product->id);
    expect($barcode->barcode)->toBe('1111111111111');
});

test('a product can have several barcodes, excluding another product\'s', function () {
    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1111111111111']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '2222222222222']);

    // Noise: a barcode belonging to a different product.
    $otherProduct = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $otherProduct->id, 'barcode' => '3333333333333']);

    expect($product->barcodes()->pluck('barcode')->sort()->values()->all())
        ->toBe(['1111111111111', '2222222222222']);
});

test('the model reads the wms-owned table', function () {
    expect((new ProductBarcode)->getTable())->toBe('wms_product_barcodes');
});
