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

    $this->withToken(storeApiToken($customer))->getJson('/temp/api/v2/payment-types')->assertOk()->assertJsonPath('payment_types.0.payment_type_key', 'cash_on_delivery');
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
    $this->postJson('/temp/api/v2/user/shipping/create', ['address' => 'Prinsengracht 2', 'country_id' => DB::table('countries')->where('code', 'NL')->value('id'), 'phone' => '0612345678'])->assertOk();

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

test('order/store tells the customer why the payment type is refused', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['cash_on_delivery' => 0]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => 1, 'variation' => '', 'price' => 1]);
    $message = function (array $body) use ($customer) {
        app('auth')->forgetGuards();
        $response = $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/order/store', $body)
            ->assertUnprocessable()->assertJsonPath('result', false)->assertJsonPath('combined_order_id', 0);
        expect(array_keys($response->json()))->toEqual(['combined_order_id', 'result', 'message']);

        return $response->json('message');
    };

    expect($message([]))->toBe(__('storefront.checkout.select_payment_option'))
        ->and($message(['payment_type' => 'bogus']))->toBe(__('api.order.payment_type_invalid'))
        ->and($message(['payment_type' => 'cash_on_delivery']))->toBe(__('api.order.payment_type_cash_disabled'));

    storeSetting('cash_payment', 1);
    expect($message(['payment_type' => 'cash_on_delivery']))->toBe(__('api.order.payment_type_cash_product'));

    DB::table('products')->where('id', $product->id)->update(['digital' => 1, 'cash_on_delivery' => 1]);
    expect($message(['payment_type' => 'cash_on_delivery']))->toBe(__('api.order.payment_type_cash_digital'));

    expect(collect([__('api.order.payment_type_cash_disabled'), __('api.order.payment_type_cash_product'), __('api.order.payment_type_cash_digital')])->unique())->toHaveCount(3);
});

test('the app saves an address under the storefront\'s rules, reading 0 as no state or city', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    addStoreAddress($customer->id);
    $country = DB::table('countries')->where('code', 'NL')->value('id');
    $state = DB::table('states')->insertGetId(['country_id' => $country, 'name' => 'North Holland', 'status' => 1]);
    $city = DB::table('cities')->insertGetId(['state_id' => $state, 'name' => 'Amsterdam', 'status' => 1]);
    $otherState = DB::table('states')->insertGetId(['country_id' => $country, 'name' => 'Utrecht', 'status' => 1]);
    $valid = ['address' => 'Prinsengracht 2', 'country_id' => $country, 'phone' => '0612345678'];
    $save = function (string $path, array $body) use ($customer) {
        app('auth')->forgetGuards();

        return $this->withToken(storeApiToken($customer))->postJson("/temp/api/v2/user/shipping/$path", $body);
    };

    $save('create', [...$valid, 'state_id' => 0, 'city_id' => 0])->assertOk()->assertJsonPath('result', true);
    $save('create', [...$valid, 'address' => 'Damrak 3', 'state_id' => $state, 'city_id' => $city])->assertOk();

    expect(DB::table('addresses')->where('address', 'Prinsengracht 2')->first(['state_id', 'city_id']))->toHaveProperties(['state_id' => null, 'city_id' => null])
        ->and(DB::table('addresses')->where('address', 'Damrak 3')->value('city_id'))->toBe($city);

    $refused = [
        'no phone' => [[...$valid, 'phone' => null], 'phone'],
        'no street address' => [[...$valid, 'address' => ''], 'address'],
        'no country' => [[...$valid, 'country_id' => null], 'country id'],
        'a state that does not exist' => [[...$valid, 'state_id' => 999999], 'state id'],
        'a city of another state' => [[...$valid, 'state_id' => $otherState, 'city_id' => $city], 'city id'],
    ];
    foreach ($refused as $case => [$body, $field]) {
        $response = $save('create', $body)->assertUnprocessable()->assertJsonPath('result', false);
        expect($response->json('message'))->not->toBeEmpty($case)->and($response->json('errors'))->toHaveKey(str_replace(' ', '_', $field));
    }

    $id = DB::table('addresses')->where('address', 'Damrak 3')->value('id');
    $save('update', [...$valid, 'id' => $id, 'address' => 'Damrak 4', 'state_id' => 0])->assertOk();
    $save('update', [...$valid, 'id' => $id, 'phone' => ''])->assertUnprocessable();
    $save('update', [...$valid, 'id' => addStoreAddress($other->id), 'address' => 'Hijacked'])->assertForbidden();

    expect(DB::table('addresses')->where('id', $id)->first(['address', 'state_id']))->toHaveProperties(['address' => 'Damrak 4', 'state_id' => null])
        ->and(DB::table('addresses')->where('address', 'Hijacked')->count())->toBe(0)
        ->and(DB::table('addresses')->where('user_id', $customer->id)->count())->toBe(3);
});

test('the cart summary prices a line whose unset rep_price comes back as the string "0.00", as MySQL returns it', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $changed = createStoreUser('customer');
    $line = fn (int $userId, int $quantity, array $attributes = []) => Cart::create([
        'user_id' => $userId, 'owner_id' => 0, 'product_id' => createStoreProduct(['unit_price' => 22])->id,
        'quantity' => $quantity, 'variation' => '', 'price' => 22, ...$attributes,
    ]);
    $line($customer->id, 2);
    $line($changed->id, 3, ['rep_price' => 20, 'is_price_changed' => true]);

    // SQLite hands a decimal back as a number; MySQL as a string.
    Cart::retrieved(fn (Cart $cart) => $cart->setRawAttributes([...$cart->getAttributes(), 'rep_price' => number_format((float) $cart->rep_price, 2, '.', '')], true));

    try {
        $this->withToken(storeApiToken($customer))->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('grand_total_value', 44);
        app('auth')->forgetGuards();
        $this->withToken(storeApiToken($changed))->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('grand_total_value', 60);
    } finally {
        Cart::flushEventListeners();
    }
});

test('the payment types say when nothing is available, and why cash is not offered for this cart', function () {
    checkoutStore();
    $customer = createStoreUser('customer');
    $off = createStoreProduct(['cash_on_delivery' => 0]);
    Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $off->id, 'quantity' => 1, 'variation' => '', 'price' => 1]);
    $types = function () use ($customer) {
        app('auth')->forgetGuards();

        return $this->withToken(storeApiToken($customer))->getJson('/temp/api/v2/payment-types')->assertOk()->json();
    };

    // Cash payment off: nothing to offer, and no cash entry to explain.
    expect($types())->toBe(['result' => true, 'payment_types' => [], 'unavailable' => [], 'message' => __('api.payment.none_available')]);

    // Cash payment on, but not for this cart: still nothing, with the reason for cash.
    storeSetting('cash_payment', 1);
    expect($types())->toBe([
        'result' => true,
        'payment_types' => [],
        'unavailable' => [['payment_type_key' => 'cash_on_delivery', 'message' => __('api.order.payment_type_cash_product')]],
        'message' => __('api.payment.none_available'),
    ]);

    // Every product allows it: cash is listed and nothing is said.
    DB::table('products')->update(['cash_on_delivery' => 1]);
    $listed = $types();
    expect(collect($listed['payment_types'])->pluck('payment_type_key')->all())->toBe(['cash_on_delivery'])
        ->and($listed['unavailable'])->toBe([])
        ->and($listed['message'])->toBeNull();

    // A digital product takes cash away again, with its own reason.
    DB::table('products')->where('id', $off->id)->update(['digital' => 1]);
    expect($types()['unavailable'][0]['message'])->toBe(__('api.order.payment_type_cash_digital'));
});
