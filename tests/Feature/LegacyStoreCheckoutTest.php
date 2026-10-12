<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

/**
 * The legacy store's cart and web checkout, end to end through the real
 * routes. None of it worked before:
 *
 * - the product card, the product page and the cart page read tables the
 *   store's own migrations created after `shop.sql` (offers,
 *   offer_products, carts_offered, product_variations) and columns they
 *   added to it, none of which had been carried over;
 * - addToCart() mass-assigned two keys (`cash_on_delivery`, `digital`) that
 *   were never cart columns, and the delivery step a `shipping_type` Cart
 *   didn't allow, both of which the legacy app's Eloquent silently dropped;
 * - a guest's add to cart then failed in the offers step, which loaded the
 *   signed-in user's offers without checking there was one;
 * - the order row left out `orders.shipping_type` and the order detail wrote
 *   a null `tax`, both NOT NULL, which the legacy app's non-strict MySQL
 *   quietly turned into '' and 0 and this app's strict connection refuses.
 */
test('a guest can add a product to their cart and open it', function () {
    seedStoreCurrency();
    $product = createStoreProduct(['unit_price' => 2.5]);

    $this->post('/temp/cart/addtocart', ['id' => $product->id, 'quantity' => 2])
        ->assertOk()
        ->assertJson(['status' => 1, 'cart_count' => 1]);

    expect(Cart::whereNotNull('temp_user_id')->get(['product_id', 'quantity', 'price'])->toArray())->toEqual([
        ['product_id' => $product->id, 'quantity' => 2, 'price' => 2.5],
    ]);

    $this->get('/temp/cart')
        ->assertOk()
        ->assertSee($product->name);
});

test("a customer's cart item is theirs alone", function () {
    seedStoreCurrency();
    $product = createStoreProduct();
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $otherCustomer = createStoreUser('customer');
    Cart::create(['user_id' => $otherCustomer->id, 'product_id' => $product->id, 'quantity' => 5]);

    $this->post('/temp/cart/addtocart', ['id' => $product->id, 'quantity' => 1])->assertOk();

    expect(Cart::where('user_id', $customer->id)->pluck('quantity')->all())->toBe([1])
        ->and(Cart::where('user_id', $otherCustomer->id)->pluck('quantity')->all())->toBe([5]);
});

test('a customer can check out their cart with cash on delivery', function () {
    seedStoreCurrency();
    // The delivery step names the store's own admin account, which every
    // real store has.
    createStoreUser('admin');
    $product = createStoreProduct(['unit_price' => 2.5, 'current_stock' => 50]);
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $addressId = addStoreAddress($customer->id);

    $this->post('/temp/cart/addtocart', ['id' => $product->id, 'quantity' => 2])->assertOk();
    $this->get('/temp/checkout')->assertOk();
    $this->post('/temp/checkout/delivery_info', ['address_id' => $addressId])->assertOk();
    $this->post('/temp/checkout/payment_select')->assertOk();
    $this->post('/temp/checkout/payment', ['payment_option' => 'cash_on_delivery'])
        ->assertRedirect(route('order_confirmed'));

    $order = Order::sole();

    expect($order->only(['user_id', 'payment_type', 'payment_status', 'delivery_status', 'shipping_type', 'grand_total']))->toEqual([
        'user_id' => $customer->id,
        'payment_type' => 'cash_on_delivery',
        'payment_status' => 'unpaid',
        'delivery_status' => 'pending',
        'shipping_type' => 'home_delivery',
        'grand_total' => 5,
    ])
        ->and(OrderDetail::where('order_id', $order->id)->get(['product_id', 'quantity', 'price', 'tax'])->toArray())->toEqual([
            ['product_id' => $product->id, 'quantity' => 2, 'price' => 5, 'tax' => 0],
        ])
        ->and(DB::table('product_stocks')->where('product_id', $product->id)->value('qty'))->toBe(48);

    $this->get(route('order_confirmed'))->assertOk();

    expect(Cart::where('user_id', $customer->id)->count())->toBe(0);
});

test('checking out with no payment option places no order', function () {
    seedStoreCurrency();
    $product = createStoreProduct();
    $customer = actingAsStoreUser(createStoreUser('customer'));
    Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'address_id' => addStoreAddress($customer->id)]);

    $this->from('/temp/checkout')->post('/temp/checkout/payment')->assertRedirect('/temp/checkout');

    expect(Order::count())->toBe(0)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(1);
});
