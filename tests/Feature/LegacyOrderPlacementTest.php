<?php

use App\Models\Cart;
use App\Models\CombinedOrder;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Placing an order (the storefront's checkout and the app's order/store and
 * wallet payment) is one transaction: stock is taken under a row lock, a
 * single-use coupon is recorded and its discount computed on the server, and
 * anything refused (stock, coupon, wallet) leaves no order, no stock taken and
 * the cart as it was.
 */
function checkoutWithCash(object $test)
{
    return $test->post('/temp/checkout/payment', ['payment_option' => 'cash_on_delivery']);
}

function placeApiOrder(object $test, string $token, array $body = ['payment_type' => 'cash_on_delivery'])
{
    app('auth')->forgetGuards();

    return $test->withToken($token)->postJson('/temp/api/v2/order/store', $body);
}

test('the storefront takes the stock of every line of an order it places', function () {
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $line = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);
    $noise = orderableCartLine(createStoreUser('customer'), createStoreUser('seller'), 1, stock: 5);

    checkoutWithCash($this)->assertRedirect(route('order_confirmed'));

    expect(Order::where('user_id', $customer->id)->count())->toBe(1)
        ->and(stockLeft($line->product_id))->toBe(3)
        ->and(stockLeft($noise->product_id))->toBe(5);
});

test('a line the shop cannot cover places no order and takes no stock from the other sellers', function () {
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $covered = orderableCartLine($customer, createStoreUser('seller'), 1, stock: 5);
    $short = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 1);

    checkoutWithCash($this)->assertRedirect(route('cart'));

    expect(Order::count())->toBe(0)
        ->and(CombinedOrder::count())->toBe(0)
        ->and(stockLeft($covered->product_id))->toBe(5)
        ->and(stockLeft($short->product_id))->toBe(1)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(2);
});

test('the last unit goes to the first customer to check out, and the second gets nothing', function () {
    seedStoreCurrency();
    createStoreUser('admin');
    $seller = createStoreUser('seller');
    $first = createStoreUser('customer');
    $second = createStoreUser('customer');
    $firstLine = orderableCartLine($first, $seller, 1, stock: 1);
    $secondLine = Cart::create([...$firstLine->only(['owner_id', 'product_id', 'quantity', 'variation', 'price', 'tax', 'shipping_cost', 'shipping_type']), 'user_id' => $second->id, 'address_id' => addStoreAddress($second->id)]);

    actingAsStoreUser($first);
    checkoutWithCash($this)->assertRedirect(route('order_confirmed'));
    actingAsStoreUser($second);
    checkoutWithCash($this)->assertRedirect(route('cart'));

    expect(Order::pluck('user_id')->all())->toBe([$first->id])
        ->and(stockLeft($firstLine->product_id))->toBe(0)
        ->and(Cart::where('user_id', $second->id)->count())->toBe(1);
});

test('a coupon takes its discount off the order, is recorded once, and cannot be used again', function () {
    seedStoreCurrency();
    createStoreUser('admin');
    $seller = createStoreUser('seller');
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $coupon = couponFor($seller, ['discount' => 1]);
    $line = orderableCartLine($customer, $seller, 2, stock: 10, cartAttributes: ['coupon_code' => $coupon->code]);

    checkoutWithCash($this)->assertRedirect(route('order_confirmed'));

    $order = Order::sole();
    expect((float) $order->coupon_discount)->toBe(1.0)
        ->and((float) $order->grand_total)->toBe(4.0)
        ->and(CouponUsage::where('user_id', $customer->id)->where('coupon_id', $coupon->id)->count())->toBe(1);

    // The same customer, a new cart carrying the same coupon.
    $again = orderableCartLine($customer, $seller, 1, stock: 10, cartAttributes: ['coupon_code' => $coupon->code]);
    Cart::where('id', $line->id)->forceDelete();
    checkoutWithCash($this)->assertRedirect(route('cart'));

    expect(Order::count())->toBe(1)
        ->and(CouponUsage::count())->toBe(1)
        ->and(stockLeft($again->product_id))->toBe(10);
});

test('an expired or unknown coupon on the cart places no order', function (array $couponAttributes, string $code) {
    seedStoreCurrency();
    createStoreUser('admin');
    $seller = createStoreUser('seller');
    $customer = actingAsStoreUser(createStoreUser('customer'));
    $coupon = couponFor($seller, $couponAttributes);
    $line = orderableCartLine($customer, $seller, 2, stock: 10, cartAttributes: ['coupon_code' => $code === '' ? $coupon->code : $code]);

    checkoutWithCash($this)->assertRedirect(route('cart'));

    expect(Order::count())->toBe(0)
        ->and(CouponUsage::count())->toBe(0)
        ->and(stockLeft($line->product_id))->toBe(10);
})->with([
    'expired' => [['end_date' => strtotime('-1 day'), 'start_date' => strtotime('-3 days')], ''],
    'unknown' => [[], 'NOSUCHCODE'],
]);

test('the app takes stock, places the order and empties the cart', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = createStoreUser('customer');
    $line = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);

    placeApiOrder($this, storeApiToken($customer))->assertOk()->assertJsonPath('result', true);

    expect(Order::sole()->user_id)->toBe($customer->id)
        ->and(stockLeft($line->product_id))->toBe(3)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(0);
});

test('the app refuses a line the shop cannot cover and keeps the cart and the other stock', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = createStoreUser('customer');
    $covered = orderableCartLine($customer, createStoreUser('seller'), 1, stock: 5);
    $short = orderableCartLine($customer, createStoreUser('seller'), 3, stock: 2);

    placeApiOrder($this, storeApiToken($customer))->assertOk()->assertJsonPath('result', false)->assertJsonPath('combined_order_id', 0);

    expect(Order::count())->toBe(0)
        ->and(CombinedOrder::count())->toBe(0)
        ->and(stockLeft($covered->product_id))->toBe(5)
        ->and(stockLeft($short->product_id))->toBe(2)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(2);
});

test('the app ignores a discount written on the cart row and computes the coupon\'s own', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    orderableCartLine($customer, $seller, 2, cartAttributes: ['discount' => 999]);
    orderableCartLine($customer, createStoreUser('seller'), 2, cartAttributes: ['discount' => 999, 'coupon_code' => 'NOSUCHCODE']);

    // A coupon code that does not exist refuses the whole order; a forged discount alone changes nothing.
    placeApiOrder($this, storeApiToken($customer))->assertJsonPath('result', false);
    expect(Order::count())->toBe(0);

    Cart::where('coupon_code', 'NOSUCHCODE')->update(['coupon_code' => '']);
    placeApiOrder($this, storeApiToken($customer))->assertJsonPath('result', true);

    expect(Order::pluck('grand_total')->map(fn ($total) => (float) $total)->sort()->values()->all())->toBe([5.0, 5.0])
        ->and(Order::sum('coupon_discount'))->toEqual(0)
        ->and(CouponUsage::count())->toBe(0);
});

test('the app uses a coupon once and takes its computed discount', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $coupon = couponFor($seller, ['discount' => 1]);
    orderableCartLine($customer, $seller, 2, cartAttributes: ['coupon_code' => $coupon->code, 'discount' => 999]);

    placeApiOrder($this, storeApiToken($customer))->assertJsonPath('result', true);

    expect((float) Order::sole()->coupon_discount)->toBe(1.0)
        ->and((float) Order::sole()->grand_total)->toBe(4.0)
        ->and(CouponUsage::count())->toBe(1);

    orderableCartLine($customer, $seller, 2, cartAttributes: ['coupon_code' => $coupon->code]);
    placeApiOrder($this, storeApiToken($customer))->assertJsonPath('result', false);

    expect(Order::count())->toBe(1)->and(CouponUsage::count())->toBe(1);
});

test('the app refuses a payment type that is not a payment option', function () {
    Notification::fake();
    createStoreUser('admin');
    $customer = createStoreUser('customer');
    $line = orderableCartLine($customer, createStoreUser('seller'), 1);

    placeApiOrder($this, storeApiToken($customer), ['payment_type' => '<script>x</script>'])->assertJsonPath('result', false);

    expect(Order::count())->toBe(0)->and(stockLeft($line->product_id))->toBe(50);
});

test('the app needs a signed-in account to place an order', function () {
    $this->postJson('/temp/api/v2/order/store', ['payment_type' => 'cash_on_delivery'])->assertUnauthorized();

    expect(Order::count())->toBe(0);
});

test('the wallet pays inside the order: the balance drops once and the order is paid', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = fundedCustomer(10);
    $bystander = fundedCustomer(10);
    $line = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);

    placeApiOrder($this, storeApiToken($customer), ['payment_type' => 'wallet'])->assertOk()->assertJsonPath('result', true);

    expect((float) $customer->fresh()->balance)->toBe(5.0)
        ->and((float) $bystander->fresh()->balance)->toBe(10.0)
        ->and(Order::sole()->payment_status)->toBe('paid')
        ->and(stockLeft($line->product_id))->toBe(3)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(0);
});

test('a wallet that cannot cover the order places no order and keeps the cart', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = fundedCustomer(1);
    $line = orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/payments/pay/wallet', ['payment_type' => 'wallet'])
        ->assertOk()
        ->assertJsonPath('result', false)
        ->assertJsonPath('message', 'Insufficient wallet balance');

    expect((float) $customer->fresh()->balance)->toBe(1.0)
        ->and(Order::count())->toBe(0)
        ->and(CombinedOrder::count())->toBe(0)
        ->and(stockLeft($line->product_id))->toBe(5)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(1);
});

test('the wallet payment through the payments route charges the order and empties the cart', function () {
    Notification::fake();
    seedStoreCurrency();
    createStoreUser('admin');
    $customer = fundedCustomer(10);
    orderableCartLine($customer, createStoreUser('seller'), 2, stock: 5);

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/payments/pay/wallet', ['payment_type' => 'wallet'])
        ->assertOk()->assertJsonPath('result', true);

    expect((float) $customer->fresh()->balance)->toBe(5.0)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(0)
        ->and(DB::table('orders')->where('payment_status', 'paid')->count())->toBe(1);
});
