<?php

use App\Models\Product;
use App\Models\ProductBarcode;

test('a worker adds a barcode and gets the product\'s barcode list back', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $existing = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'OLD-CODE']);

    $response = $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => '  NEW-CODE-1  ']);

    $response->assertCreated();
    $new = ProductBarcode::query()->where('barcode', 'NEW-CODE-1')->sole();
    expect($new->product_id)->toBe($product->id);
    expect($response->json('data'))->toEqual([
        'product_id' => $product->id,
        'barcode' => ['id' => $new->id, 'barcode' => 'NEW-CODE-1', 'printable' => true],
        'barcodes' => [
            ['id' => $existing->id, 'barcode' => 'OLD-CODE', 'printable' => true],
            ['id' => $new->id, 'barcode' => 'NEW-CODE-1', 'printable' => true],
        ],
    ]);
    $response->assertHeaderMissing('ETag');
});

test('an unauthenticated request is refused', function () {
    $product = Product::factory()->create();

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'CODE'])->assertUnauthorized();

    expect(ProductBarcode::query()->where('barcode', 'CODE')->exists())->toBeFalse();
});

test('an unknown product is a 404', function () {
    actingAsMobileUser();

    $this->postJson('/api/v1/products/999999/barcodes', ['barcode' => 'CODE'])->assertNotFound();
    $this->postJson('/api/v1/products/abc/barcodes', ['barcode' => 'CODE'])->assertNotFound();
});

test('a missing or malformed barcode is a 422', function (mixed $barcode) {
    actingAsMobileUser();
    $product = Product::factory()->create();

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => $barcode])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('barcode');

    expect(ProductBarcode::query()->where('product_id', $product->id)->count())->toBe(0);
})->with([
    'null' => [null],
    'blank' => ['   '],
    'too long' => [str_repeat('A', 65)],
    'not printable ascii' => ["caf\u{e9}"],
    'array' => [['A']],
]);

test('a code the product already holds is a 422, like the WMS form', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'HELD-CODE']);

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'HELD-CODE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('barcode');

    expect(ProductBarcode::query()->where('barcode', 'HELD-CODE')->count())->toBe(1);
});

test('a code another product holds is a 409 barcode_taken with the holders', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $holderA = Product::factory()->create();
    $holderB = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $holderB->id, 'barcode' => 'SHARED-CODE']);
    ProductBarcode::factory()->create(['product_id' => $holderA->id, 'barcode' => 'SHARED-CODE']);

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'SHARED-CODE'])
        ->assertConflict()
        ->assertExactJson([
            'message' => __('messages.barcode_taken'),
            'error_code' => 'barcode_taken',
            'product_ids' => [$holderA->id, $holderB->id],
        ]);

    expect(ProductBarcode::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('the 409 message is translated', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['barcode' => 'SHARED-CODE']);

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'SHARED-CODE'], ['Accept-Language' => 'ar'])
        ->assertConflict()
        ->assertJsonPath('message', trans('messages.barcode_taken', [], 'ar'));
});

test('the product\'s own-id row is neither listed nor removed', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $ownId = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => (string) $product->id]);

    $response = $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'EXTRA-CODE']);

    $response->assertCreated();
    expect(collect($response->json('data.barcodes'))->pluck('barcode')->all())->toBe(['EXTRA-CODE']);
    expect(ProductBarcode::query()->whereKey($ownId->id)->exists())->toBeTrue();
});

test('another product\'s id is refused as a new barcode', function () {
    actingAsMobileUser();
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => (string) $other->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('barcode');
});

test('the added barcode then resolves the product through the lookup', function () {
    actingAsMobileUser();
    $product = Product::factory()->create(['name' => 'Widget']);

    $this->postJson("/api/v1/products/{$product->id}/barcodes", ['barcode' => 'SCAN-ME-42'])->assertCreated();

    $this->getJson('/api/v1/products/SCAN-ME-42')
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);
});
