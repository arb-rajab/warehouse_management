<?php

use App\Models\Product;

test('an authenticated worker can list products with every property the app reads', function () {
    actingAsMobileUser();

    $product = Product::factory()->create([
        'name' => 'Widget',
        'image_url' => 'https://cdn.example.com/widget.png',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => null,
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('the product listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('an unauthenticated caller cannot list products', function () {
    $response = $this->getJson('/api/v1/products');

    $response->assertUnauthorized();
});
