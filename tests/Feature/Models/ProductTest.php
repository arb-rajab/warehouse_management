<?php

use App\Models\Product;

test('a product can be created with its fillable attributes', function () {
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://example.com/widgets.png',
    ]);

    expect($product->fresh())
        ->name->toBe('Widgets')
        ->image_url->toBe('https://example.com/widgets.png');
});

test('a product can be created without an image_url', function () {
    $product = Product::factory()->create(['image_url' => null]);

    expect($product->fresh()->image_url)->toBeNull();
});
