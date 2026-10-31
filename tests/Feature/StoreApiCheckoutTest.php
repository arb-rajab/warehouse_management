<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * The app's checkout reads, as the app's developers re-tested them on dev:
 *
 * - GET /cart-summary answered 0 for a cart whose line was priced;
 * - GET /delivery-info added every earlier seller's lines to the next seller's
 *   subtotal, the figure the minimum order is checked against;
 * - a representative who had picked a customer, but not yet added a product,
 *   was still shown (and wrote) the representative's own addresses.
 */
function checkoutStore(): void
{
    seedStoreCurrency();
    storeSetting('no_of_decimals', 2);
    createStoreUser('admin');
}

/**
 * A catalogue product as the sync commands build it: a per-box and a per-piece
 * stock row.
 */
function boxedProduct(float $unitPrice, int $unitEqual = 12, array $attributes = []): int
{
    $product = createStoreProduct(['unit_price' => $unitPrice, 'unit_equal' => $unitEqual, 'current_stock' => 1000, ...$attributes]);
    DB::table('product_stocks')->where('product_id', $product->id)->update(['variant' => 'PerBox']);
    DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => 'PerPiece', 'sku' => 'piece-'.$product->id, 'price' => $unitPrice / $unitEqual, 'qty' => 1000]);

    return $product->id;
}

test('the cart summary totals the lines the cart lists, for a product with a box and a piece stock', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $noise = createStoreUser('customer');
    Cart::create(['user_id' => $noise->id, 'owner_id' => 0, 'product_id' => boxedProduct(99), 'quantity' => 5, 'variation' => '', 'price' => 99]);
    $this->withToken(storeApiToken($customer));

    $this->postJson('/temp/api/v2/carts/add', ['id' => boxedProduct(22), 'variant' => '', 'quantity' => 2])->assertOk()->assertJsonPath('result', true);

    $this->postJson('/temp/api/v2/carts', [])->assertOk()->assertJsonPath('0.cart_items.0.price', 22)->assertJsonPath('0.cart_items.0.quantity', 2);
    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJson([
        'sub_total' => '44.00€',
        'grand_total' => '44.00€',
        'grand_total_value' => 44,
        'delivery_cost' => '0.00€',
    ]);
});

test('a stock row with no price does not make an unvaried cart line free', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $product = boxedProduct(22);
    DB::table('product_stocks')->where('product_id', $product)->update(['price' => 0]);
    $this->withToken(storeApiToken($customer));

    $this->postJson('/temp/api/v2/carts/add', ['id' => $product, 'variant' => '', 'quantity' => 2])->assertOk();

    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('grand_total_value', 44);
    $this->getJson('/temp/api/v2/delivery-info')->assertOk()->assertJsonPath('0.subtotal', 44);
});

test('the delivery cost is charged only when the store checks a minimum order and its delivery product exists', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $delivery = createStoreProduct(['unit_price' => 7.5, 'mat_id' => 'TRANSPORT']);
    storeSetting('default_specific_product', 'TRANSPORT');
    storeSetting('minimum_order_amount', 100);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => createStoreProduct(['unit_price' => 10])->id, 'quantity' => 1, 'variation' => '', 'price' => 10]);
    $this->withToken(storeApiToken($customer));

    storeSetting('minimum_order_amount_check', 0);
    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('delivery_cost', '0.00€');

    storeSetting('minimum_order_amount_check', 1);
    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('delivery_cost', '7.50€');

    $delivery->delete();
    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('delivery_cost', '0.00€');
});

test('the delivery info totals each seller\'s own lines', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $first = createStoreUser('seller');
    $second = createStoreUser('seller');
    Cart::create(['user_id' => $customer->id, 'owner_id' => $first->id, 'product_id' => createStoreProduct(['user_id' => $first->id, 'unit_price' => 10])->id, 'quantity' => 2, 'variation' => '', 'price' => 10]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => $second->id, 'product_id' => createStoreProduct(['user_id' => $second->id, 'unit_price' => 3])->id, 'quantity' => 1, 'variation' => '', 'price' => 3]);

    $shops = $this->withToken(storeApiToken($customer))->getJson('/temp/api/v2/delivery-info')->assertOk()->json();

    expect(collect($shops)->pluck('subtotal', 'owner_id')->all())->toEqual([$first->id => 20, $second->id => 3]);
});

test('the payment types skip a cart line whose product is gone', function () {
    checkoutStore();
    storeSetting('cash_payment', 1);
    $customer = createStoreUser('customer');
    $orderable = createStoreProduct(['cash_on_delivery' => 1]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $orderable->id, 'quantity' => 1, 'variation' => '', 'price' => 1]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => 999999, 'quantity' => 1, 'variation' => '', 'price' => 1]);

    $this->withToken(storeApiToken($customer))->getJson('/temp/api/v2/payment-types')->assertOk()->assertJsonPath('0.payment_type_key', 'cash_on_delivery');
});

test('a representative who picked a customer works on the customer\'s addresses before adding a product', function () {
    checkoutStore();
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    $repAddress = addStoreAddress($rep->id);
    $customerAddress = addStoreAddress($customer->id);
    $otherAddress = addStoreAddress($other->id);
    $this->withToken(storeApiToken($rep));

    expect($this->getJson('/temp/api/v2/user/shipping/address')->assertOk()->json('data.*.id'))->toBe([$repAddress]);

    // The guard keeps its user, and the user its loaded relations, between the
    // requests of one test; a real request starts clean.
    app('auth')->forgetGuards();
    $this->postJson('/temp/api/v2/representatives/pick-customer', ['id' => $customer->id, 'latitude' => 52.37, 'longitude' => 4.89])->assertOk()->assertJsonPath('result', true);

    app('auth')->forgetGuards();
    expect($this->getJson('/temp/api/v2/user/shipping/address')->assertOk()->json('data.*.id'))->toBe([$customerAddress]);
    app('auth')->forgetGuards();
    expect($this->getJson('/temp/api/v2/representatives/current-customer')->assertOk()->json('data.id'))->toBe($customer->id);

    app('auth')->forgetGuards();
    $this->postJson('/temp/api/v2/user/shipping/create', ['address' => 'Prinsengracht 2', 'country_id' => 1, 'state_id' => 2, 'city_id' => 3, 'phone' => '0612345678'])->assertOk();

    expect(Address::where('address', 'Prinsengracht 2')->value('user_id'))->toBe($customer->id)
        ->and(Address::where('user_id', $rep->id)->count())->toBe(1)
        ->and(Address::where('user_id', $other->id)->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $customerAddress])->assertOk()->assertJsonPath('result', true);
    app('auth')->forgetGuards();
    $this->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $otherAddress])->assertForbidden();
});

test('a representative with no open visit and no cart stays on their own addresses', function () {
    checkoutStore();
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();
    $customer = createStoreUser('customer');
    $repAddress = addStoreAddress($rep->id);
    addStoreAddress($customer->id);
    // A visit that already led to an order, and one from a day ago, are not open.
    Visit::create(['rep_id' => $rep->id, 'customer_id' => $customer->id, 'order_id' => 1, 'visit_date' => now()]);
    $old = Visit::create(['rep_id' => $rep->id, 'customer_id' => $customer->id, 'visit_date' => now()->subDays(2)]);
    DB::table('visits')->where('id', $old->id)->update(['created_at' => now()->subDays(2)]);

    $this->withToken(storeApiToken($rep))->getJson('/temp/api/v2/user/shipping/address')->assertOk()->assertJsonPath('data.*.id', [$repAddress]);
});
