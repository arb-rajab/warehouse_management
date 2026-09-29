<?php

use App\Models\Product;

/**
 * Regression coverage for Product::product_translations()/getTranslation(),
 * merged in from the legacy store's own Product model (see
 * .ai/rules/legacy-albaraka-holland.md section 2). Unlike most of that
 * merge's other relations (category(), storeBarcodes(), Upload::user()),
 * this one needs no column beyond what the local `products`/
 * `product_translations` stand-in tables already carry, so it's the one
 * actually exercisable before the DB-merge phase adds the rest.
 */
test('getTranslation returns the matching-locale translation', function () {
    $product = Product::create(['name' => 'Widget', 'ar_name' => 'ويدجت']);
    $product->product_translations()->create([
        'name' => 'Widget FR', 'unit' => 'pc', 'description' => 'desc fr', 'lang' => 'fr',
    ]);

    // Noise: another product's own French translation must never leak into
    // this product's lookup.
    $noiseProduct = Product::create(['name' => 'Gadget', 'ar_name' => 'أداة']);
    $noiseProduct->product_translations()->create([
        'name' => 'Gadget FR', 'unit' => 'pc', 'description' => 'noise', 'lang' => 'fr',
    ]);

    expect($product->getTranslation('name', 'fr'))->toBe('Widget FR')
        ->and($product->product_translations)->toHaveCount(1);
});

test('getTranslation falls back to the base column when no translation exists for the locale', function () {
    $product = Product::create(['name' => 'Widget', 'ar_name' => 'ويدجت']);
    $product->product_translations()->create([
        'name' => 'Widget FR', 'unit' => 'pc', 'description' => 'desc fr', 'lang' => 'fr',
    ]);

    expect($product->getTranslation('name', 'en'))->toBe('Widget');
});
