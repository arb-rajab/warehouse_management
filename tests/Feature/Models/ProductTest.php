<?php

use App\Models\Product;

test('a product can be created with its fillable attributes', function () {
    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://example.com/widgets.png',
        'boxes_count' => 24,
    ]);

    expect($product->fresh())
        ->name->toBe('Widgets')
        ->image_url->toBe('https://example.com/widgets.png')
        ->boxes_count->toBe(24);
});

test('a product can be created without an image_url', function () {
    $product = Product::factory()->create(['image_url' => null]);

    expect($product->fresh()->image_url)->toBeNull();
});

test('selectedOptions returns only the given ids, ordered by name, excluding an unselected product', function () {
    $b = Product::factory()->create(['name' => 'Bravo']);
    $a = Product::factory()->create(['name' => 'Alpha']);
    Product::factory()->create(['name' => 'Unselected Charlie']);

    $options = Product::selectedOptions([$a->id, $b->id]);

    expect($options->pluck('name')->all())->toBe(['Alpha', 'Bravo']);
});

test('selectedOptions returns an empty collection when given no ids', function () {
    Product::factory()->create();

    expect(Product::selectedOptions())->toBeEmpty();
});
