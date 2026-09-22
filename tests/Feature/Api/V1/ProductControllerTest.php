<?php

use App\Models\Product;

test('an authenticated worker can list products with every property the app reads', function () {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('an Arabic-locale client gets the same raw name columns as an English one', function () {
    // The payload no longer varies by `Accept-Language`: both store columns
    // ship raw and the client picks. See .ai/rules/shared-database.md.
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    // Noise: another product's names must not be the ones returned.
    Product::factory()->create(['name' => 'Gadget', 'ar_name' => 'أداة']);

    $response = $this->getJson('/api/v1/products', ['Accept-Language' => 'ar']);

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
});

test('a product the store never translated ships an empty ar_name rather than a null one', function () {
    // `ar_name` is NOT NULL upstream, so an untranslated product carries an
    // empty string — that is exactly what the frontend resolver falls back on.
    actingAsMobileUser();

    $product = Product::factory()->imageUrl(null)->boxesCount(6)->create([
        'name' => 'Widget',
        'ar_name' => '',
    ]);

    $response = $this->getJson('/api/v1/products', ['Accept-Language' => 'ar']);

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => '',
        'image_url' => null,
        'boxes_count' => 6,
        'active' => true,
    ]);
});

test('an unpublished product ships active as false', function () {
    actingAsMobileUser();

    $product = Product::factory()->create(['name' => 'Widget', 'published' => false]);

    $response = $this->getJson('/api/v1/products?product_status=inactive');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id)['active'])->toBeFalse();
});

test('the product listing excludes inactive products by default', function () {
    actingAsMobileUser();

    $active = Product::factory()->create(['name' => 'Active Widget']);
    // Noise: an inactive product must not appear when no product_status filter is given.
    $inactive = Product::factory()->inactive()->create(['name' => 'Inactive Widget']);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))
        ->toContain($active->id)
        ->not->toContain($inactive->id);
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
    // Searching matches either column; the response ships both raw, so an
    // Arabic term still comes back with the base `name` alongside it.
    expect($response->json('data.0.name'))->toBe('Widgets');
    expect($response->json('data.0.ar_name'))->toBe('ودجات');
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

test('the product listing can be filtered by product_status=active, excluding an inactive product', function () {
    actingAsMobileUser();

    $activeProduct = Product::factory()->create(['name' => 'Active Widget']);
    Product::factory()->inactive()->create(['name' => 'Inactive Widget']);

    $response = $this->getJson('/api/v1/products?product_status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($activeProduct->id);
});

test('the product listing can be filtered by product_status=inactive, excluding an active product', function () {
    actingAsMobileUser();

    Product::factory()->create(['name' => 'Active Widget']);
    $inactiveProduct = Product::factory()->inactive()->create(['name' => 'Inactive Widget']);

    $response = $this->getJson('/api/v1/products?product_status=inactive');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($inactiveProduct->id);
});

test('an invalid product_status is rejected on the product listing', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/products?product_status=bogus');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_status']);
});

test('an unauthenticated caller cannot list products', function () {
    $response = $this->getJson('/api/v1/products');

    $response->assertUnauthorized();
});

test('an authenticated worker can fetch a single product by id', function () {
    actingAsMobileUser();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->boxesCount(12)->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    // Noise: a second product must not leak into this response.
    Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk();
    expect($response->json())->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
        'image_url' => 'https://cdn.example.com/widget.png',
        'boxes_count' => 12,
        'active' => true,
    ]);
});

test('fetching a deactivated product by id returns it normally with active false, not a 404', function () {
    actingAsMobileUser();

    $product = Product::factory()->inactive()->create(['name' => 'Widget']);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk();
    expect($response->json('active'))->toBeFalse();
});

test('fetching a nonexistent product id returns a 404', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/products/999999');

    $response->assertNotFound();
});

test('an unauthenticated caller cannot fetch a single product', function () {
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertUnauthorized();
});
