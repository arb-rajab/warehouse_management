<?php

use App\Models\Product;

test('an authenticated worker can list products with every property the app reads', function () {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('the product listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/products');

    assertJsonListingPaginates($response, total: 25);
});

test('the product listing filters by name, excluding a non-matching product', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q=Widg');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing filters by the store\'s Arabic name, excluding a non-matching product', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('ودجات'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
    // `ar_name` is searchable only — the payload the app reads is unchanged.
    expect($response->json('data.0.name'))->toBe('Widgets');
});

test('the product listing matches an Arabic term sent with an English Accept-Language header', function () {
    // The searched columns are locale-independent: a worker with the app in
    // English still finds a product by the Arabic name printed on its box.
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('ودجات'), ['Accept-Language' => 'en']);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing matches a multi-word term split across the two name columns', function () {
    actingAsMobileUser();

    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة زرقاء كبيرة']);
    // Noise: matches only the English half of the term.
    Product::factory()->create(['name' => 'Small Widget', 'ar_name' => 'ودجة صغيرة']);

    $response = $this->getJson('/api/v1/products?q='.urlencode('Widget زرقاء'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product listing returns every product for a blank term', function () {
    actingAsMobileUser();

    Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/api/v1/products?q=');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('an unauthenticated caller cannot list products', function () {
    $response = $this->getJson('/api/v1/products');

    $response->assertUnauthorized();
});
