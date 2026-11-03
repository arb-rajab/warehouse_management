<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductCollection;
use App\Models\StoreUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * VAT is added on top of the line price, the product answers hide prices from a
 * guest and from an unverified customer, and a client that asks gets 422 for
 * the refusals the legacy app reads as 200 with result:false.
 */
beforeEach(function () {
    Cache::flush();
    seedStoreCurrency();
    storeSetting('no_of_decimals', 2);
    storeSetting('cash_payment', 1);
    createStoreUser('admin');
});

function vatVerifiedCustomer(): StoreUser
{
    return createStoreUser('customer');
}

function vatUnverifiedCustomer(): StoreUser
{
    return createStoreUser('customer', verified: false);
}

/** A cart line for `$customer`: `$quantity` of a EUR 10 product with `$vat` percent VAT. */
function vatLine(StoreUser $customer, int $quantity, float $vat): Cart
{
    $collection = ProductCollection::create(['name' => 'Margin', 'serial' => 'C'.random_int(1, 9999), 'amount' => 10]);
    $product = createStoreProduct(['unit_price' => 10, 'tax' => $vat, 'cash_on_delivery' => 1, 'collection_id' => $collection->id]);

    return Cart::create([
        'user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => $quantity, 'variation' => '',
        'price' => 10, 'tax' => 0, 'shipping_cost' => 0, 'shipping_type' => 'home_delivery', 'address_id' => addStoreAddress($customer->id),
    ]);
}

// ---- BR-20261103-B.4: VAT on top ---------------------------------------------------------------

test('the cart adds VAT on top of each line and shows the percent and the amount apart', function () {
    $customer = vatVerifiedCustomer();
    $line = vatLine($customer, 2, 9);
    vatLine(createStoreUser('customer'), 5, 21); // another account's cart: not counted
    $token = storeApiToken($customer);

    $summary = $this->withToken($token)->getJson('/api/v1/store/cart-summary')->assertOk()->json();
    expect($summary['grand_total_value'])->toEqual(21.8);

    app('auth')->forgetGuards();
    $row = $this->withToken($token)->postJson('/api/v1/store/carts')->assertOk()->json('0.cart_items.0');
    expect($row['id'])->toBe($line->id)
        ->and($row['tax_percent'])->toEqual(9)
        ->and($row['tax_amount'])->toEqual(1.8)
        ->and($row['price'])->toEqual(10);
});

test('an order adds the VAT to its total and records the amount, not the percent, as the money', function () {
    $customer = vatVerifiedCustomer();
    vatLine($customer, 2, 9);
    $untaxed = vatLine($customer, 1, 0);
    $token = storeApiToken($customer);

    $this->withToken($token)->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertOk()->assertJsonPath('result', true);

    $order = Order::sole();
    $lines = OrderDetail::where('order_id', $order->id)->orderBy('id')->get();
    expect((float) $order->grand_total)->toEqual(31.8) // 20 + 1.80 + 10
        ->and($lines->map(fn ($line) => [(float) $line->tax, (float) $line->tax_amount])->all())->toEqual([[9.0, 1.8], [0.0, 0.0]])
        ->and($untaxed->product_id)->toBe($lines[1]->product_id);

    app('auth')->forgetGuards();
    $details = $this->withToken($token)->getJson('/api/v1/store/purchase-history-details/'.$order->id)->assertOk()->json('data.0');
    expect($details['tax'])->toBe(format_price(1.8))
        ->and($details['subtotal'])->toBe(format_price(30))
        ->and($details['grand_total'])->toBe(format_price(31.8));

    app('auth')->forgetGuards();
    $items = $this->withToken($token)->getJson('/api/v1/store/purchase-history-items/'.$order->id)->assertOk()->json('data');
    expect(collect($items)->map(fn ($item) => [$item['tax'], $item['tax_percent']])->all())->toEqual([[format_price(1.8), 9], [format_price(0), 0]]);
});

// ---- BR-20261103-B.6: prices only for verified customers -------------------------------------

function vatFeaturedProduct(): int
{
    return createStoreProduct(['published' => 1, 'approved' => 1, 'featured' => 1, 'added_by' => 'admin', 'digital' => 0, 'unit_price' => 7.5])->id;
}

test('a guest and an unverified customer get null prices and a flag, a verified customer gets the prices', function (string $caller) {
    $id = vatFeaturedProduct();
    $tokens = ['guest' => null, 'unverified' => storeApiToken(vatUnverifiedCustomer()), 'verified' => storeApiToken(vatVerifiedCustomer())];
    $request = $tokens[$caller] === null ? $this : $this->withToken($tokens[$caller]);

    $response = $request->getJson('/api/v1/store/products/featured')->assertOk();
    $product = collect($response->json('data'))->firstWhere('id', $id);
    $hidden = $caller !== 'verified';

    expect($product)->not->toBeNull()
        ->and($product['main_price'])->when($hidden, fn ($price) => $price->toBeNull(), fn ($price) => $price->not->toBeNull())
        ->and($product['stroked_price'])->when($hidden, fn ($price) => $price->toBeNull(), fn ($price) => $price->not->toBeNull())
        ->and($product['calculable_price'])->when($hidden, fn ($price) => $price->toBeNull(), fn ($price) => $price->not->toBeNull())
        ->and($response->json('price_hidden'))->toBe($hidden ? true : null)
        ->and($response->headers->get('X-Prices-Hidden'))->toBe($hidden ? '1' : null)
        ->and($product['name'])->not->toBeEmpty();
})->with(['guest', 'unverified', 'verified']);

test('a representative and a seller see prices without being verified customers', function (string $type) {
    $id = vatFeaturedProduct();
    $user = createStoreUser($type === 'rep' ? 'customer' : 'seller');
    $user->forceFill(['is_rep' => $type === 'rep'])->save();

    $product = collect($this->withToken(storeApiToken($user))->getJson('/api/v1/store/products/featured')->assertOk()->json('data'))->firstWhere('id', $id);

    expect($product['main_price'])->not->toBeNull();
})->with(['rep', 'seller']);

test('the cart of an unverified customer shows no money', function () {
    $customer = vatUnverifiedCustomer();
    vatLine($customer, 2, 9);
    $token = storeApiToken($customer);

    $summary = $this->withToken($token)->getJson('/api/v1/store/cart-summary')->assertOk();
    expect($summary->json('grand_total'))->toBeNull()
        ->and($summary->json('grand_total_value'))->toBeNull()
        ->and($summary->json('sub_total'))->toBeNull()
        ->and($summary->json('price_hidden'))->toBeTrue()
        ->and($summary->json('coupon_applied'))->toBeFalse();

    app('auth')->forgetGuards();
    $line = $this->withToken($token)->postJson('/api/v1/store/carts')->assertOk()->json('0.cart_items.0');
    expect($line['price'])->toBeNull()->and($line['tax_amount'])->toBeNull()->and($line['quantity'])->toBe(2);
});

// ---- BR-20261103-B.7: 422 for the refusals the legacy app reads as 200 ---------------------------

test('a client that sends X-Status-Mode: http gets 422 for a refusal that is otherwise 200', function () {
    $customer = vatVerifiedCustomer();
    $token = storeApiToken($customer);
    $product = createStoreProduct();
    $body = ['id' => $product->id, 'variant' => '', 'quantity' => 0];

    $legacy = $this->withToken($token)->postJson('/api/v1/store/carts/add', $body)->assertOk();
    app('auth')->forgetGuards();
    $strict = $this->withToken($token)->withHeader('X-Status-Mode', 'http')->postJson('/api/v1/store/carts/add', $body)->assertUnprocessable();

    expect($strict->json())->toEqual($legacy->json())->and($strict->json('result'))->toBeFalse()
        ->and(DB::table('carts')->count())->toBe(0);
});

test('X-Status-Mode: http leaves successes and other statuses alone', function () {
    $customer = vatVerifiedCustomer();
    $product = createStoreProduct();

    $this->withToken(storeApiToken($customer))->withHeader('X-Status-Mode', 'http')->postJson('/api/v1/store/carts/add', ['id' => $product->id, 'variant' => '', 'quantity' => 1])
        ->assertOk()->assertJsonPath('result', true);
    app('auth')->forgetGuards();
    $this->flushHeaders()->withHeader('X-Status-Mode', 'http')->postJson('/api/v1/store/carts/add', ['id' => $product->id, 'variant' => '', 'quantity' => 1])->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($customer))->withHeader('X-Status-Mode', 'http')->postJson('/api/v1/store/carts/add', ['id' => 999999, 'variant' => '', 'quantity' => 1])->assertNotFound();
});

// ---- An unverified customer cannot order what it cannot see the price of -----------------------

test('an unverified customer is refused with 403 and no order is placed', function () {
    $customer = vatUnverifiedCustomer();
    vatLine($customer, 1, 9);
    $verified = vatVerifiedCustomer();
    vatLine($verified, 1, 9);

    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertForbidden()->assertJsonPath('result', false)->assertJsonPath('message', __('api.order.account_not_verified'));

    expect(Order::count())->toBe(0)->and(Cart::where('user_id', $customer->id)->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($verified))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertOk()->assertJsonPath('result', true);
    expect(Order::count())->toBe(1);
});

// ---- VAT on top in the web checkout and the back office entry ----------------------------------

test('the web checkout adds the VAT to the order total and records the amount', function () {
    $customer = actingAsStoreUser(vatVerifiedCustomer());
    $line = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);
    $line->product->forceFill(['tax' => 10])->save();
    $noise = orderableCartLine(createStoreUser('customer'), createStoreUser('seller'), 1, stock: 5);

    $this->post('/temp/checkout/payment', ['payment_option' => 'cash_on_delivery'])->assertRedirect(route('order_confirmed'));

    $order = Order::where('user_id', $customer->id)->sole();
    $detail = OrderDetail::where('order_id', $order->id)->sole();
    expect((float) $order->grand_total)->toEqual(5.5) // 2 x 2.50 + 10 percent
        ->and((float) $detail->tax)->toEqual(10.0)
        ->and((float) $detail->tax_amount)->toEqual(0.5)
        ->and(Order::count())->toBe(1)
        ->and($noise->fresh())->not->toBeNull();
});

// ---- Offers: the offer line carries its own VAT -----------------------------------------------

test('an offer line records and is charged its own VAT, not the VAT of the line it was copied from', function () {
    $customer = vatVerifiedCustomer();
    $ordered = vatLine($customer, 2, 9);
    $offered = createStoreProduct(['unit_price' => 4, 'tax' => 21, 'cash_on_delivery' => 1]);
    DB::table('carts_offered')->insert([
        'user_id' => $customer->id, 'owner_id' => 0, 'address_id' => $ordered->address_id, 'product_id' => $offered->id,
        'variation' => '', 'variation_qty' => '[]', 'price' => 4, 'quantity' => 1, 'is_postponed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $noise = vatVerifiedCustomer();
    DB::table('carts_offered')->insert([
        'user_id' => $noise->id, 'owner_id' => 0, 'address_id' => 1, 'product_id' => $offered->id,
        'variation' => '', 'variation_qty' => '[]', 'price' => 4, 'quantity' => 1, 'is_postponed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertOk()->assertJsonPath('result', true);

    $order = Order::sole();
    $lines = OrderDetail::where('order_id', $order->id)->orderBy('id')->get()->keyBy('product_id');
    expect((float) $lines[$ordered->product_id]->tax_amount)->toEqual(1.8)
        ->and((float) $lines[$offered->id]->tax_amount)->toEqual(0.84) // 21 percent of 4, not the 1.80 of the ordered line
        ->and((float) $order->grand_total)->toEqual(26.64) // 20 + 4 + 1.80 + 0.84
        ->and(DB::table('carts_offered')->where('user_id', $noise->id)->count())->toBe(1);
});
