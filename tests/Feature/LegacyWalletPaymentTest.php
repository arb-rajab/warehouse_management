<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Services\WalletPaymentService;

/**
 * The mobile API's wallet payment charged the user named in the request the
 * amount named in the request, while the order was priced from the cart and
 * marked paid: anyone could pay with another account's balance, or send
 * `amount=0` and pay nothing. The payer is the signed-in account and the amount
 * is the order's own total now (WalletPaymentService).
 */
function fundedCustomer(float $balance): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['balance' => $balance])->save();

    return $customer;
}

/**
 * A combined order of `$total` that `$customer` placed, with one order marked paid.
 *
 * @return array{0: CombinedOrder, 1: Order}
 */
function paidCombinedOrder(StoreUser $customer, float $total): array
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    $order = createStoreOrder($customer, ['combined_order_id' => $combined->id, 'payment_status' => 'paid', 'grand_total' => $total]);

    return [$combined, $order];
}

test('the wallet pays the order\'s own total, and no one else\'s balance moves', function () {
    $payer = fundedCustomer(100);
    $bystander = fundedCustomer(100);
    [$combined, $order] = paidCombinedOrder($payer, 30.5);

    expect(app(WalletPaymentService::class)->charge($payer, $combined->id))->toBeTrue()
        ->and((float) $payer->fresh()->balance)->toBe(69.5)
        ->and((float) $bystander->fresh()->balance)->toBe(100.0)
        ->and($order->fresh()->payment_status)->toBe('paid');
});

test('a balance that does not cover the order is left alone and the order goes back to unpaid', function () {
    $payer = fundedCustomer(10);
    [$combined, $order] = paidCombinedOrder($payer, 30);

    expect(app(WalletPaymentService::class)->charge($payer, $combined->id))->toBeFalse()
        ->and((float) $payer->fresh()->balance)->toBe(10.0)
        ->and($order->fresh()->payment_status)->toBe('unpaid');
});

test('an order that belongs to someone else costs the payer nothing', function () {
    $payer = fundedCustomer(50);
    $owner = fundedCustomer(50);
    [$combined] = paidCombinedOrder($owner, 40);

    expect(app(WalletPaymentService::class)->charge($payer, $combined->id))->toBeFalse();

    expect((float) $payer->fresh()->balance)->toBe(50.0)
        ->and((float) $owner->fresh()->balance)->toBe(50.0);
});

test('naming a funded account in the request does not borrow its balance', function () {
    $attacker = fundedCustomer(0);
    $victim = fundedCustomer(100);

    $this->withToken(storeApiToken($attacker))
        ->postJson('/temp/api/v2/payments/pay/wallet', ['user_id' => $victim->id, 'amount' => 10, 'payment_type' => 'wallet'])
        ->assertOk()
        ->assertJsonPath('result', false)
        ->assertJsonPath('message', 'Insufficient wallet balance');

    expect((float) $victim->fresh()->balance)->toBe(100.0)
        ->and(Order::count())->toBe(0);
});

test('the wallet payment needs a signed-in account', function () {
    $victim = fundedCustomer(100);

    $this->postJson('/temp/api/v2/payments/pay/wallet', ['user_id' => $victim->id, 'amount' => 10])->assertUnauthorized();

    expect((float) $victim->fresh()->balance)->toBe(100.0);
});

test('the storefront checkout refuses a payment option that names a gateway the store has not enabled', function (string $option) {
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);

    // Refused by the checkout (a redirect back) or, for a name with backslashes, by the request filter (403).
    expect($this->post(route('payment.checkout'), ['payment_option' => $option])->getStatusCode())->toBeIn([302, 403])
        ->and(CombinedOrder::count())->toBe(0);
})->with([
    'a gateway that is off' => ['aamarpay'],
    'another gateway that is off' => ['sslcommerz'],
    'a name that is not a plain option' => ['..\\..\\Foo'],
]);

test('the storefront checkout leaves an enabled gateway to the gateway', function () {
    config(['store.enabled_payment_gateways' => ['payhere']]);
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);

    // No cart: the order step is reached (and fails on the empty cart) rather than refused up front.
    $response = $this->post(route('payment.checkout'), ['payment_option' => 'payhere']);

    expect($response->getStatusCode())->not->toBe(302);
});
