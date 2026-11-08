<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Support\Facades\DB;

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

test('the model reads the one barcode table the store shares', function () {
    $product = Product::factory()->create();
    // A row the store wrote (the Otajer import, the store form) is a ProductBarcode too.
    $storeRow = DB::table('product_barcodes')->insertGetId(['product_id' => $product->id, 'barcode' => 'STORE-WRITTEN']);

    expect((new ProductBarcode)->getTable())->toBe('product_barcodes')
        ->and(ProductBarcode::query()->findOrFail($storeRow)->barcode)->toBe('STORE-WRITTEN');
});

test('distinctValues keeps the first row of each trimmed value and drops blanks', function () {
    $product = Product::factory()->create();
    $first = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'DUP-1']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => ' DUP-1 ']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '   ']);
    $other = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'OTHER']);

    expect(ProductBarcode::distinctValues($product->barcodes)->modelKeys())->toBe([$first->id, $other->id])
        ->and(ProductBarcode::distinctValues($product->barcodes)->keys()->all())->toBe([0, 1]);
});
