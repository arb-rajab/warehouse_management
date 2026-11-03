<?php

use App\Models\Cart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The dev catalogue holds imported rows that cannot be opened: no stock row
 * (so "0€" and stock 0) or no owner row. They must not reach a list, and a
 * detail request answers 404 JSON (or opens) rather than a 500.
 */
beforeEach(function () {
    Cache::flush();
});

function deadProduct(array $attributes = []): int
{
    $product = createStoreProduct([
        'published' => 1, 'approved' => 1, 'added_by' => 'admin', 'digital' => 0, 'num_of_sale' => 999,
        'unit_price' => 0, 'current_stock' => 0, ...$attributes,
    ]);
    DB::table('product_stocks')->where('product_id', $product->id)->delete();

    return $product->id;
}

test('a product without a stock row is left out of the lists and answers 404 JSON', function () {
    $store = strictApiStore();
    $dead = deadProduct(['user_id' => $store['admin']->id, 'name' => 'cumin dead']);
    $client = fn () => tap($this, fn () => app('auth')->forgetGuards())->withToken(storeApiToken($store['customer']));

    expect($client()->getJson('/api/v1/store/products/best-seller')->assertOk()->json('data.*.id'))->not->toContain($dead)
        ->and($client()->getJson('/api/v1/store/products/search?name=cumin')->assertOk()->json('data.*.id'))->not->toContain($dead);

    $client()->getJson("/api/v1/store/products/$dead")->assertNotFound()->assertJsonStructure(['message']);
    $client()->getJson('/api/v1/store/products/987654')->assertNotFound()->assertJsonStructure(['message']);
});

test('a product whose owner row is gone opens as an in-house product', function () {
    $store = strictApiStore();
    $orphan = createStoreProduct(['published' => 1, 'approved' => 1, 'added_by' => 'seller', 'user_id' => 999999, 'digital' => 0]);

    $this->withToken(storeApiToken($store['customer']))->getJson("/api/v1/store/products/{$orphan->id}")
        ->assertOk()->assertJsonPath('data.0.seller_id', 0)->assertJsonPath('data.0.shop_id', 0);
});

test('a guest is answered 401 JSON on the checkout reads', function (string $path) {
    strictApiStore();

    $this->get($path)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
})->with(['/api/v1/store/payment-types', '/api/v1/store/delivery-info', '/api/v1/store/cart-summary']);

test('the cart summary prices a line from the catalogue, not from the stored cart price', function () {
    $store = strictApiStore();
    Cart::where('user_id', $store['customer']->id)->update(['price' => 0]);

    $total = $this->withToken(storeApiToken($store['customer']))->getJson('/api/v1/store/cart-summary')
        ->assertOk()->json('grand_total_value');

    expect($total)->toBeGreaterThan(0);
});

test('the product lists and detail keep the mat_id key as the product id', function () {
    $store = strictApiStore();
    $first = createStoreProduct(['published' => 1, 'approved' => 1, 'added_by' => 'admin', 'digital' => 0, 'user_id' => $store['admin']->id, 'name' => 'saffron one', 'num_of_sale' => 5]);
    $second = createStoreProduct(['published' => 1, 'approved' => 1, 'added_by' => 'admin', 'digital' => 0, 'user_id' => $store['admin']->id, 'name' => 'saffron two', 'num_of_sale' => 4]);
    $client = fn () => tap($this, fn () => app('auth')->forgetGuards())->withToken(storeApiToken($store['customer']));

    foreach (['best-seller', 'search?name=saffron'] as $path) {
        $rows = collect($client()->getJson("/api/v1/store/products/$path")->assertOk()->json('data'))->keyBy('id');

        expect($rows->keys()->all())->toContain($first->id, $second->id)
            ->and($rows[$first->id]['mat_id'])->toBe((string) $first->id)
            ->and($rows[$second->id]['mat_id'])->toBe((string) $second->id);
    }

    $client()->getJson("/api/v1/store/products/{$first->id}")->assertOk()->assertJsonPath('data.0.mat_id', (string) $first->id);
});
