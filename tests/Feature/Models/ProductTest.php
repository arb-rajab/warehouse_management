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

test('filterOptions returns every product ordered by name', function () {
    $c = Product::factory()->create(['name' => 'Charlie']);
    $a = Product::factory()->create(['name' => 'Alpha']);
    $b = Product::factory()->create(['name' => 'Bravo']);

    $options = Product::filterOptions();

    expect($options->pluck('name')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
    expect($options->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);
});

test('filterOptions returns an empty collection when there are no products', function () {
    expect(Product::filterOptions())->toBeEmpty();
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

test('searchByName filters to products whose name contains the term, excluding a non-matching product', function () {
    $matching = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unrelated Gadgets']);

    $results = Product::query()->searchByName('Widg')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('searchByName matches every product when the term is null or blank', function () {
    Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Gadgets']);

    expect(Product::query()->searchByName(null)->count())->toBe(2);
    expect(Product::query()->searchByName('')->count())->toBe(2);
});

test('searchByName matches multi-word terms regardless of word order, excluding a partial match', function () {
    $matching = Product::factory()->create(['name' => 'Large Blue Widget']);
    Product::factory()->create(['name' => 'Large Red Widget']);

    $results = Product::query()->searchByName('Widget Blue')->get();

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});
