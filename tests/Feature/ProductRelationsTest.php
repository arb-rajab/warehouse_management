<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreProductBarcode;

/**
 * Regression coverage for Product::category()/storeBarcodes() (merged from
 * the legacy store's own Product model, see
 * .ai/rules/legacy-albaraka-holland.md section 2). Both were untestable
 * until the migrations adding products.category_id and the product_barcodes
 * table landed -- see those migrations' own docblocks for why the columns
 * were missing in the first place.
 */
test('category() resolves the product\'s own category, not another product\'s', function () {
    $category = Category::forceCreate(['name' => 'Electronics']);
    $noiseCategory = Category::forceCreate(['name' => 'Furniture']);

    $product = Product::create(['name' => 'Widget', 'ar_name' => 'ويدجت']);
    $product->category_id = $category->id;
    $product->save();

    $noiseProduct = Product::create(['name' => 'Gadget', 'ar_name' => 'أداة']);
    $noiseProduct->category_id = $noiseCategory->id;
    $noiseProduct->save();

    expect($product->category->id)->toBe($category->id)
        ->and($product->category->id)->not->toBe($noiseCategory->id);
});

test('storeBarcodes() resolves only the product\'s own barcodes', function () {
    $product = Product::create(['name' => 'Widget', 'ar_name' => 'ويدجت']);
    $noiseProduct = Product::create(['name' => 'Gadget', 'ar_name' => 'أداة']);

    StoreProductBarcode::create(['product_id' => $product->id, 'barcode' => '1111111111']);
    StoreProductBarcode::create(['product_id' => $product->id, 'barcode' => '2222222222']);
    StoreProductBarcode::create(['product_id' => $noiseProduct->id, 'barcode' => 'NOISE']);

    $barcodes = $product->storeBarcodes->pluck('barcode');

    expect($product->storeBarcodes)->toHaveCount(2)
        ->and($barcodes)->toContain('1111111111')
        ->and($barcodes)->toContain('2222222222')
        ->and($barcodes)->not->toContain('NOISE');
});
