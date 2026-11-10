<?php

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * A buyer can ask for a combination of choices the product has no stock row
 * for: a stale product page, a variant removed since, or seed data that
 * declares more choices than it stocks. That is an out-of-stock answer, not a
 * server error.
 */
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

function variantProduct(): Product
{
    $product = createStoreProduct([
        'choice_options' => json_encode([['attribute_id' => '1', 'values' => ['Large']]]),
        'published' => 1,
        'approved' => 1,
        'added_by' => 'admin',
    ]);
    DB::table('product_stocks')->where('product_id', $product->id)->update(['variant' => 'Large']);

    return $product;
}

test('the price of a stocked combination is answered with its stock', function () {
    $product = variantProduct();

    $this->postJson(route('products.variant_price'), ['id' => $product->id, 'quantity' => 1, 'attribute_id_1' => 'Large'])
        ->assertOk()
        ->assertJson(['in_stock' => 1, 'variation' => 'Large', 'max_limit' => 50]);
});

test('the price of a combination with no stock row is answered as out of stock, not a server error', function () {
    $product = variantProduct();

    $this->postJson(route('products.variant_price'), ['id' => $product->id, 'quantity' => 1, 'attribute_id_1' => 'Small'])
        ->assertOk()
        ->assertExactJson([
            'price' => single_price(0),
            'quantity' => 0,
            'digital' => 0,
            'variation' => 'Small',
            'max_limit' => 0,
            'in_stock' => 0,
        ]);
});

test('adding a stocked combination to the cart puts it in the cart', function () {
    $customer = createStoreUser('customer');
    $product = variantProduct();
    actingAsStoreUser($customer);

    $this->postJson(route('cart.addToCart'), ['id' => $product->id, 'quantity' => 1, 'attribute_id_1' => 'Large'])
        ->assertOk()
        ->assertJson(['status' => 1]);

    expect(Cart::query()->where('user_id', $customer->id)->where('product_id', $product->id)->count())->toBe(1);
});

test('adding a combination with no stock row answers out of stock and adds nothing to the cart', function () {
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    $product = variantProduct();
    cartLine($other, createStoreUser('admin'), 1);
    actingAsStoreUser($customer);

    $response = $this->postJson(route('cart.addToCart'), ['id' => $product->id, 'quantity' => 1, 'attribute_id_1' => 'Small'])
        ->assertOk()
        ->assertJson(['status' => 0]);

    expect($response->json('modal_view'))->toContain(__('storefront.product.this_item_is_out_of_stock'))
        ->and(Cart::query()->where('user_id', $customer->id)->count())->toBe(0)
        ->and(Cart::query()->where('user_id', $other->id)->count())->toBe(1);
});
