<?php

use App\Http\Controllers\Payment\WalletController as WalletPaymentController;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\VerifiedPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * The store's payment gateways credited whatever their callbacks said: the
 * order, the payer and the amount came from the request (or the session) and
 * the sinks (checkout_done, wallet_payment_done) applied them as often as they
 * were called. SSLCommerz even signed the browser in as the account its post
 * named. Every callback now asks the gateway's own server, binds the answer to
 * the payment this session (or, for the mobile API, the signed-in account or
 * the payment its `begin` recorded) started, takes the amount due from the
 * database, and credits once (GatewayPaymentService).
 */
beforeEach(function () {
    config(['store.enabled_payment_gateways' => ['aamarpay', 'bkash', 'iyzico', 'nagad', 'ngenius', 'payhere', 'sslcommerz', 'voguepay']]);
    Http::preventStrayRequests();
    Sleep::fake();
    $_SERVER['SSLCZ_STORE_ID'] = 'store-1';
    $_SERVER['SSLCZ_STORE_PASSWD'] = 'store-pass';
});

afterEach(function () {
    unset($_SERVER['SSLCZ_STORE_ID'], $_SERVER['SSLCZ_STORE_PASSWD']);
});

function gatewayCustomer(float $balance = 0): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['balance' => $balance])->save();

    return $customer->fresh();
}

/**
 * An unpaid combined order of `$total` that `$customer` placed, split over `$parts` orders.
 */
function unpaidGatewayOrder(StoreUser $customer, float $total, int $parts = 1): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);

    foreach (range(1, $parts) as $part) {
        createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total / $parts]);
    }

    return $combined;
}

/**
 * @return list<string>
 */
function gatewayOrderStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

function expectOrderUnpaid(CombinedOrder $combined): void
{
    expect(gatewayOrderStatuses($combined))->toBe(['unpaid'])
        ->and(Order::query()->where('combined_order_id', $combined->id)->where('commission_calculated', 1)->count())->toBe(0);
}

function expectOrderPaidOnce(CombinedOrder $combined): void
{
    expect(gatewayOrderStatuses($combined))->toBe(['paid'])
        ->and(Order::query()->where('combined_order_id', $combined->id)->where('commission_calculated', 0)->count())->toBe(0)
        ->and(GatewayPayment::query()->where('combined_order_id', $combined->id)->count())->toBe(1);
}

/**
 * @param  array<string, mixed>  $session
 */
function cartSession(CombinedOrder $combined, array $session = []): array
{
    return ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id] + $session;
}

/**
 * A success post as SSLCommerz signs it: an md5 over the `verify_key` fields and
 * the md5 of the store password.
 *
 * @param  array<string, string>  $fields
 * @return array<string, string>
 */
function sslcommerzPost(string $tranId, string $amount, array $fields = [], string $password = 'store-pass'): array
{
    $post = $fields + ['tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount, 'status' => 'VALID'];
    $post['verify_key'] = implode(',', array_keys($post));

    $signed = array_intersect_key($post, array_flip(explode(',', $post['verify_key'])));
    $signed['store_passwd'] = md5($password);
    ksort($signed);
    $post['verify_sign'] = md5(implode('&', array_map(fn ($key, $value) => "$key=$value", array_keys($signed), $signed)));

    return $post;
}

function fakeSslcommerzValidation(string $tranId, string $amount, string $status = 'VALID'): void
{
    Http::fake(['*sslcommerz.com/validator/*' => Http::response([
        'status' => $status, 'tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount,
        'currency_type' => 'BDT', 'currency_amount' => $amount,
    ])]);
}

// --- the shared sink ---------------------------------------------------------

test('a verified payment pays every part of the payer\'s order once and runs the commission once', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30, parts: 2);
    $service = app(GatewayPaymentService::class);

    expect($service->complete(new VerifiedPayment('test', 'REF-1', 30), $customer->id, GatewayPaymentService::CART, $combined->id))->toBeTrue()
        ->and($service->complete(new VerifiedPayment('test', 'REF-1', 30), $customer->id, GatewayPaymentService::CART, $combined->id))->toBeFalse()
        ->and($service->complete(new VerifiedPayment('test', 'REF-2', 30), $customer->id, GatewayPaymentService::CART, $combined->id))->toBeFalse();

    expectOrderPaidOnce($combined);
});

test('the sink refuses another account\'s order, a short amount and an order already paid, and records nothing', function () {
    $owner = gatewayCustomer();
    $stranger = gatewayCustomer();
    $combined = unpaidGatewayOrder($owner, 30);
    $paid = unpaidGatewayOrder($owner, 10);
    Order::query()->where('combined_order_id', $paid->id)->update(['payment_status' => 'paid']);
    $service = app(GatewayPaymentService::class);

    expect($service->complete(new VerifiedPayment('test', 'A', 30), $stranger->id, GatewayPaymentService::CART, $combined->id))->toBeFalse()
        ->and($service->complete(new VerifiedPayment('test', 'B', 29), $owner->id, GatewayPaymentService::CART, $combined->id))->toBeFalse()
        ->and($service->complete(new VerifiedPayment('test', 'C', 10), $owner->id, GatewayPaymentService::CART, $paid->id))->toBeFalse()
        ->and(GatewayPayment::count())->toBe(0);

    expectOrderUnpaid($combined);
});

test('a whole-unit charge for a fractional total pays it, and nothing less does', function () {
    expect(GatewayPaymentService::covers(30, 30.4))->toBeTrue()
        ->and(GatewayPaymentService::covers(30.6, 30.6))->toBeTrue()
        ->and(GatewayPaymentService::covers(30, 30.6))->toBeFalse()
        ->and(GatewayPaymentService::covers(0, 0))->toBeFalse();
});

test('a wallet top-up adds the verified amount once', function () {
    $customer = gatewayCustomer(5);
    $bystander = gatewayCustomer(5);
    $service = app(GatewayPaymentService::class);

    expect($service->complete(new VerifiedPayment('test', 'TOPUP-1', 20), $customer->id, GatewayPaymentService::WALLET))->toBeTrue()
        ->and($service->complete(new VerifiedPayment('test', 'TOPUP-1', 20), $customer->id, GatewayPaymentService::WALLET))->toBeFalse()
        ->and((float) $customer->fresh()->balance)->toBe(25.0)
        ->and((float) $bystander->fresh()->balance)->toBe(5.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('the storefront wallet pays the order once and is not charged for an order already paid', function () {
    $customer = gatewayCustomer(100);
    $combined = unpaidGatewayOrder($customer, 30);
    Auth::shouldUse('store');
    Auth::guard('store')->setUser($customer);
    session(cartSession($combined));

    app(WalletPaymentController::class)->pay();
    app(WalletPaymentController::class)->pay();

    expect((float) $customer->fresh()->balance)->toBe(70.0);
    expectOrderPaidOnce($combined);
});

// --- SSLCommerz (storefront) -------------------------------------------------

test('SSLCommerz: a post its validation server does not confirm pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeSslcommerzValidation('T-1', '30.00', 'INVALID_TRANSACTION');

    $this->withSession(cartSession($combined, ['sslcommerz_tran_id' => 'T-1']))
        ->post('/temp/sslcommerz/success', sslcommerzPost('T-1', '30.00'));

    expectOrderUnpaid($combined);
});

test('SSLCommerz: a post for another transaction, or naming another order, pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    fakeSslcommerzValidation('T-OTHER', '30.00');

    $this->withSession(cartSession($combined, ['sslcommerz_tran_id' => 'T-1']))
        ->post('/temp/sslcommerz/success', sslcommerzPost('T-OTHER', '30.00', ['value_b' => (string) $other->id, 'value_c' => 'cart_payment']));

    expectOrderUnpaid($combined);
    expectOrderUnpaid($other);
});

test('SSLCommerz: a top-up post naming another account neither signs it in nor credits it', function () {
    $victim = gatewayCustomer(0);
    fakeSslcommerzValidation('T-1', '500.00');

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 500], 'sslcommerz_tran_id' => 'T-1'])
        ->post('/temp/sslcommerz/success', sslcommerzPost('T-1', '500.00', ['value_c' => 'wallet_payment', 'value_b' => '500', 'value_d' => (string) $victim->id]));

    $this->assertGuest('store');
    expect((float) $victim->fresh()->balance)->toBe(0.0)
        ->and(Wallet::count())->toBe(0);
});

test('SSLCommerz: a validated post pays the session\'s order once', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeSslcommerzValidation('T-1', '30.00');

    $this->withSession(cartSession($combined, ['sslcommerz_tran_id' => 'T-1']))
        ->post('/temp/sslcommerz/success', sslcommerzPost('T-1', '30.00'))
        ->assertRedirect(route('order_confirmed'));
    $this->withSession(cartSession($combined, ['sslcommerz_tran_id' => 'T-1']))
        ->post('/temp/sslcommerz/success', sslcommerzPost('T-1', '30.00'));

    expectOrderPaidOnce($combined);
});

test('SSLCommerz: the IPN handler and its route are gone', function () {
    $this->post('/temp/sslcommerz/ipn')->assertNotFound();
});

// --- aamarpay ----------------------------------------------------------------

function fakeAamarpaySearch(string $tranId, string $amount, string $status = 'Successful'): void
{
    Http::fake(['*aamarpay.com/api/v1/trxcheck/*' => Http::response(['pay_status' => $status, 'mer_txnid' => $tranId, 'amount' => $amount])]);
}

test('aamarpay: a success post its search API does not report paid pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeAamarpaySearch('A-1', '30.00', 'Failed');

    $this->withSession(cartSession($combined, ['aamarpay_tran_id' => 'A-1']))
        ->post(route('aamarpay.success'), ['mer_txnid' => 'A-1', 'opt_a' => 'cart_payment', 'opt_b' => $combined->id]);

    expectOrderUnpaid($combined);
});

test('aamarpay: a post naming an order or a top-up amount of its own pays only what the search API reports for this session', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    fakeAamarpaySearch('A-1', '10.00');

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 10], 'aamarpay_tran_id' => 'A-1'])
        ->post(route('aamarpay.success'), ['mer_txnid' => 'A-1', 'opt_a' => 'cart_payment', 'opt_b' => $other->id, 'opt_c' => json_encode(['amount' => 9999])]);

    expectOrderUnpaid($other);
    expect((float) $customer->fresh()->balance)->toBe(10.0);
});

test('aamarpay: a reported payment pays the session\'s order once', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeAamarpaySearch('A-1', '30.00');

    foreach ([1, 2] as $attempt) {
        $this->withSession(cartSession($combined, ['aamarpay_tran_id' => 'A-1']))
            ->post(route('aamarpay.success'), ['mer_txnid' => 'A-1']);
    }

    expectOrderPaidOnce($combined);
});

// --- bKash (storefront) ------------------------------------------------------

/**
 * @param  array<string, string>  $result
 */
function fakeBkash(array $result): void
{
    Http::fake([
        '*bka.sh/*checkout/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*bka.sh/*checkout/execute' => Http::response($result),
        '*bka.sh/*checkout/payment/status' => Http::response($result),
    ]);
}

test('bKash: the success page pays nothing that callback() did not verify', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);

    $this->withSession(cartSession($combined))->get(route('bkash.success'));

    expectOrderUnpaid($combined);
});

test('bKash: a response without status code 0000 is not a success', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeBkash(['transactionStatus' => 'Completed', 'amount' => '30']);

    $this->withSession(cartSession($combined, ['bkash_payment_id' => 'PAY-1', 'bkash_token' => 't']))
        ->get(route('bkash.callback', ['paymentID' => 'PAY-1', 'status' => 'success']));
    $this->get(route('bkash.success'));

    expectOrderUnpaid($combined);
});

test('bKash: a callback for a payment this session did not create pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeBkash(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '30']);

    $this->withSession(cartSession($combined, ['bkash_payment_id' => 'PAY-1', 'bkash_token' => 't']))
        ->get(route('bkash.callback', ['paymentID' => 'PAY-SOMEONE-ELSES', 'status' => 'success']));
    $this->get(route('bkash.success'));

    expectOrderUnpaid($combined);
});

test('bKash: a completed payment pays the session\'s order once', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeBkash(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '30']);

    $this->withSession(cartSession($combined, ['bkash_payment_id' => 'PAY-1', 'bkash_token' => 't']))
        ->get(route('bkash.callback', ['paymentID' => 'PAY-1', 'status' => 'success']))
        ->assertRedirect(route('bkash.success'));
    $this->get(route('bkash.success'))->assertRedirect(route('order_confirmed'));
    $this->get(route('bkash.success'));
    $this->withSession(['bkash_payment_id' => 'PAY-1'])->get(route('bkash.callback', ['paymentID' => 'PAY-1', 'status' => 'success']));
    $this->get(route('bkash.success'));

    expectOrderPaidOnce($combined);
});

// --- iyzico (storefront) -----------------------------------------------------

/**
 * @param  array{paid?: bool, paid_price?: float, basket_id?: string}  $payment
 */
function fakeIyzico(array $payment): void
{
    test()->mock(IyzicoPayments::class)
        ->shouldReceive('retrieve')
        ->andReturn($payment + ['paid' => true, 'paid_price' => 0.0, 'basket_id' => 'BASKET-1', 'raw' => '{}']);
}

test('iyzico: a token for another basket pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeIyzico(['paid_price' => 30.0, 'basket_id' => 'BASKET-ELSEWHERE']);

    $this->withSession(cartSession($combined, ['iyzico_basket_id' => 'BASKET-1']))
        ->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'TOKEN-1']);

    expectOrderUnpaid($combined);
});

test('iyzico: the amount and the order in the callback URL are ignored', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    fakeIyzico(['paid_price' => 25.0]);

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'iyzico_basket_id' => 'BASKET-1'])
        ->post('/temp/iyzico/payment/callback/cart_payment/1000/iyzico/'.$other->id, ['token' => 'TOKEN-1']);

    expectOrderUnpaid($other);
    expect((float) $customer->fresh()->balance)->toBe(25.0);
});

test('iyzico: a successful payment pays the session\'s order once per token', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeIyzico(['paid_price' => 30.0]);

    foreach ([1, 2] as $attempt) {
        $this->withSession(cartSession($combined, ['iyzico_basket_id' => 'BASKET-1']))
            ->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'TOKEN-1']);
    }

    expectOrderPaidOnce($combined);
});

// --- PayHere -----------------------------------------------------------------

/**
 * @param  array<string, string>  $overrides
 */
function postPayhere(string $route, array $overrides): void
{
    $post = $overrides + [
        'merchant_id' => 'MERCHANT-1', 'payment_id' => 'PH-1', 'payhere_currency' => 'LKR', 'status_code' => '2',
    ];
    $post['md5sig'] = strtoupper(md5($post['merchant_id'].$post['order_id'].$post['payhere_amount'].$post['payhere_currency'].$post['status_code'].strtoupper(md5('real-secret'))));
    $_POST = $post;

    try {
        test()->post(route($route));
    } finally {
        $_POST = [];
    }
}

test('PayHere: the order is the signed order_id, and an unsigned custom_1 naming another order is ignored', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);
    $combined = unpaidGatewayOrder(gatewayCustomer(), 25);
    $other = unpaidGatewayOrder(gatewayCustomer(), 25);

    postPayhere('payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => '25.00', 'custom_1' => (string) $other->id]);

    expectOrderPaidOnce($combined);
    expectOrderUnpaid($other);
});

test('PayHere: a signed notification for less than the order total pays nothing', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);
    $combined = unpaidGatewayOrder(gatewayCustomer(), 25);

    postPayhere('payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => '1.00']);

    expectOrderUnpaid($combined);
});

test('PayHere: a repeated payment_id pays nothing more', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);
    $customer = gatewayCustomer(0);

    postPayhere('payhere.wallet.notify', ['order_id' => 'W'.$customer->id.'-abc', 'payhere_amount' => '25.00']);
    postPayhere('payhere.wallet.notify', ['order_id' => 'W'.$customer->id.'-abc', 'payhere_amount' => '25.00']);

    expect((float) $customer->fresh()->balance)->toBe(25.0)
        ->and(Wallet::count())->toBe(1);
});

test('PayHere: a wallet notification for an account that does not exist credits nothing', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);

    postPayhere('payhere.wallet.notify', ['order_id' => 'W999999-abc', 'payhere_amount' => '25.00']);

    expect(Wallet::count())->toBe(0);
});

test('PayHere: a package is activated once, and only when the signed amount pays its price', function () {
    config(['services.payhere.secret' => 'real-secret', 'services.payhere.merchant_id' => 'MERCHANT-1']);
    $customer = gatewayCustomer();
    $package = CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => 50, 'product_upload' => 10]);

    postPayhere('payhere.customer_package_payment.notify', ['order_id' => 'P'.$customer->id.'-'.$package->id.'-abc', 'payhere_amount' => '1.00', 'payment_id' => 'PH-SHORT']);
    expect($customer->fresh()->customer_package_id)->toBeNull();

    postPayhere('payhere.customer_package_payment.notify', ['order_id' => 'P'.$customer->id.'-'.$package->id.'-abc', 'payhere_amount' => '50.00', 'payment_id' => 'PH-FULL']);
    postPayhere('payhere.customer_package_payment.notify', ['order_id' => 'P'.$customer->id.'-'.$package->id.'-abc', 'payhere_amount' => '50.00', 'payment_id' => 'PH-FULL']);

    expect($customer->fresh()->only(['customer_package_id', 'remaining_uploads']))->toEqual(['customer_package_id' => $package->id, 'remaining_uploads' => 10]);
});

// --- N-Genius ----------------------------------------------------------------

function fakeNgenius(string $reference, int $minorAmount, string $state = 'CAPTURED'): void
{
    Http::fake([
        'identity-uat.ngenius-payments.com/*' => Http::response(['access_token' => 'token']),
        'api-gateway-uat.ngenius-payments.com/*' => Http::response([
            'reference' => $reference, 'amount' => ['value' => $minorAmount], '_embedded' => ['payment' => [['state' => $state]]],
        ]),
    ]);
}

test('N-Genius: a captured order this session did not create pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeNgenius('REF-ELSEWHERE', 3000);

    $this->withSession(cartSession($combined, ['ngenius_order_ref' => 'REF-1']))
        ->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-ELSEWHERE']));

    expectOrderUnpaid($combined);
});

test('N-Genius: an order captured for less than the total pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeNgenius('REF-1', 100);

    $this->withSession(cartSession($combined, ['ngenius_order_ref' => 'REF-1']))
        ->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']));

    expectOrderUnpaid($combined);
});

test('N-Genius: the session\'s captured order pays once', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeNgenius('REF-1', 3000);

    foreach ([1, 2] as $attempt) {
        $this->withSession(cartSession($combined, ['ngenius_order_ref' => 'REF-1']))
            ->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']));
    }

    expectOrderPaidOnce($combined);
});

// --- VoguePay ----------------------------------------------------------------

function fakeVoguepay(string $transactionId, string $merchantRef, string $total): void
{
    Http::fake(['voguepay.com*' => Http::response([
        'response_message' => 'Approved', 'transaction_id' => $transactionId, 'merchant_ref' => $merchantRef, 'total' => $total,
    ])]);
}

test('VoguePay: an approved transaction from another payment form pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeVoguepay('TX-1', 'REF-ELSEWHERE', '30');

    $this->withSession(cartSession($combined, ['voguepay_merchant_ref' => 'REF-1']))->get('/temp/vogue-pay/success/TX-1');

    expectOrderUnpaid($combined);
});

test('VoguePay: the session\'s approved transaction pays once', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeVoguepay('TX-1', 'REF-1', '30');

    foreach ([1, 2] as $attempt) {
        $this->withSession(cartSession($combined, ['voguepay_merchant_ref' => 'REF-1']))->get('/temp/vogue-pay/success/TX-1');
    }

    expectOrderPaidOnce($combined);
});

// --- Nagad (storefront) ------------------------------------------------------

function fakeNagadVerify(string $orderId, string $paymentRefId, string $amount, string $status = 'Success'): void
{
    Http::fake(['*api/dfs/verify/payment/*' => Http::response([
        'status' => $status, 'orderId' => $orderId, 'paymentRefId' => $paymentRefId, 'amount' => $amount,
    ])]);
}

test('Nagad: a successful payment for another Nagad order pays nothing', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeNagadVerify('Inv-ELSEWHERE', 'NREF-1', '30');

    $this->withSession(cartSession($combined, ['nagad_order_id' => 'Inv-1']))
        ->get(route('nagad.callback', ['merchant' => 'm', 'order_id' => 'Inv-1', 'payment_ref_id' => 'NREF-1']));

    expectOrderUnpaid($combined);
});

test('Nagad: the session\'s payment pays once, its reference read from the query string', function () {
    $customer = actingAsStoreUser(gatewayCustomer());
    $combined = unpaidGatewayOrder($customer, 30);
    fakeNagadVerify('Inv-1', 'NREF-1', '30');

    foreach ([1, 2] as $attempt) {
        // payment_ref_id first: the old parser took the third query parameter, whatever it was
        $this->withSession(cartSession($combined, ['nagad_order_id' => 'Inv-1']))
            ->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1', 'merchant' => 'm', 'order_id' => 'Inv-1']));
    }

    expectOrderPaidOnce($combined);
});

// --- mobile API: bKash ---------------------------------------------------------

test('API bKash: the unauthenticated process endpoint is gone', function () {
    $victim = gatewayCustomer(0);

    // No route takes a POST there any more (another route's path answers 405 for it).
    expect($this->postJson('/temp/api/v2/bkash/api/process', ['payment_type' => 'wallet_payment', 'user_id' => $victim->id, 'amount' => 1000])->getStatusCode())->toBeIn([404, 405]);

    expect((float) $victim->fresh()->balance)->toBe(0.0);
});

test('API bKash: reporting a payment needs a signed-in account', function () {
    $combined = unpaidGatewayOrder(gatewayCustomer(), 30);

    $this->postJson('/temp/api/v2/bkash/api/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'payment_id' => 'PAY-1'])->assertUnauthorized();

    expectOrderUnpaid($combined);
});

test('API bKash: a payment bKash has not completed pays nothing', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    fakeBkash(['statusMessage' => 'Not executed', 'amount' => '30']);

    $this->withToken(storeApiToken($customer))
        ->postJson('/temp/api/v2/bkash/api/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'payment_id' => 'PAY-1']);

    expectOrderUnpaid($combined);
});

test('API bKash: a completed payment reported for another account\'s order pays nothing', function () {
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    fakeBkash(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '30']);

    $this->withToken(storeApiToken(gatewayCustomer()))
        ->postJson('/temp/api/v2/bkash/api/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $other->id, 'payment_id' => 'PAY-2', 'user_id' => $other->user_id]);

    expectOrderUnpaid($other);
});

test('API bKash: a completed payment pays the signed-in customer\'s order once', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    fakeBkash(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '30']);
    $request = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'payment_id' => 'PAY-1'];

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/bkash/api/success', $request)->assertJsonPath('result', true);
    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/bkash/api/success', $request);

    expectOrderPaidOnce($combined);
});

test('API bKash: begin prices the signed-in customer\'s own order, not someone else\'s', function () {
    $customer = gatewayCustomer();
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);

    $this->withToken(storeApiToken($customer))
        ->getJson('/temp/api/v2/bkash/begin?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $other->id]))
        ->assertJsonPath('result', false);
});

// --- mobile API: Nagad ---------------------------------------------------------

test('API Nagad: the unauthenticated process endpoint is gone', function () {
    $combined = unpaidGatewayOrder(gatewayCustomer(), 30);

    // No route takes a POST there any more (another route's path answers 405 for it).
    expect($this->postJson('/temp/api/v2/nagad/process', ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id])->getStatusCode())->toBeIn([404, 405]);

    expectOrderUnpaid($combined);
});

test('API Nagad: begin records nothing for another account\'s order', function () {
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);

    $this->withToken(storeApiToken(gatewayCustomer()))
        ->getJson('/temp/api/v2/nagad/begin?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $other->id]))
        ->assertJsonPath('result', false);

    expect(GatewayPayment::count())->toBe(0);
});

test('API Nagad: a payment for an order begin() did not record pays nothing', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    fakeNagadVerify('Inv-UNKNOWN', 'NREF-1', '30');

    $this->getJson('/temp/api/v2/nagad/verify/cart_payment?payment_ref_id=NREF-1')->assertJsonPath('result', false);

    expectOrderUnpaid($combined);
});

test('API Nagad: a payment for less than begin() recorded pays nothing', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    fakeNagadVerify('Inv-1', 'NREF-1', '1');

    $this->getJson('/temp/api/v2/nagad/verify/cart_payment?payment_ref_id=NREF-1')->assertJsonPath('result', false);

    expectOrderUnpaid($combined);
});

test('API Nagad: the payment begin() recorded is paid once', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    fakeNagadVerify('Inv-1', 'NREF-1', '30');

    $this->getJson('/temp/api/v2/nagad/verify/cart_payment?payment_ref_id=NREF-1')->assertJsonPath('result', true);
    $this->getJson('/temp/api/v2/nagad/verify/cart_payment?payment_ref_id=NREF-1')->assertJsonPath('result', false);

    expectOrderPaidOnce($combined);
});

// --- mobile API: SSLCommerz ----------------------------------------------------

test('API SSLCommerz: begin needs a signed-in account and records nothing for another account\'s order', function () {
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    $query = '?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $other->id, 'user_id' => $other->user_id]);

    $this->getJson('/temp/api/v2/sslcommerz/begin'.$query)->assertUnauthorized();
    $this->withToken(storeApiToken(gatewayCustomer()))->getJson('/temp/api/v2/sslcommerz/begin'.$query)->assertJsonPath('result', false);

    expect(GatewayPayment::count())->toBe(0);
});

test('API SSLCommerz: a post for a transaction begin() did not record pays nothing, whatever its value_ fields say', function () {
    $victim = gatewayCustomer(0);
    fakeSslcommerzValidation('AIZ-FORGED', '1000.00');

    $this->post('/temp/api/v2/sslcommerz/success', sslcommerzPost('AIZ-FORGED', '1000.00', ['value_a' => (string) $victim->id, 'value_c' => 'wallet_payment', 'value_d' => '1000']))
        ->assertJsonPath('result', false);

    expect((float) $victim->fresh()->balance)->toBe(0.0);
});

test('API SSLCommerz: a validated transaction pays what begin() recorded, once', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    fakeSslcommerzValidation('AIZ-1', '30.00');

    $this->post('/temp/api/v2/sslcommerz/success', sslcommerzPost('AIZ-1', '30.00'))->assertJsonPath('result', true);
    $this->post('/temp/api/v2/sslcommerz/success', sslcommerzPost('AIZ-1', '30.00'))->assertJsonPath('result', false);

    expectOrderPaidOnce($combined);
});

test('API SSLCommerz: a transaction the validation server does not confirm pays nothing', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    fakeSslcommerzValidation('AIZ-1', '30.00', 'FAILED');

    $this->post('/temp/api/v2/sslcommerz/success', sslcommerzPost('AIZ-1', '30.00'))->assertJsonPath('result', false);

    expectOrderUnpaid($combined);
});

// --- mobile API: iyzico ----------------------------------------------------------

test('API iyzico: starting and reporting a payment need a signed-in account', function () {
    $combined = unpaidGatewayOrder(gatewayCustomer(), 30);

    $this->getJson('/temp/api/v2/iyzico/init?payment_type=cart_payment&combined_order_id='.$combined->id)->assertUnauthorized();
    $this->postJson('/temp/api/v2/iyzico/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'token' => 'T'])->assertUnauthorized();

    expectOrderUnpaid($combined);
});

test('API iyzico: a payment iyzico reports for less, or for another account\'s order, pays nothing', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    $other = unpaidGatewayOrder(gatewayCustomer(), 30);
    $token = storeApiToken($customer);

    fakeIyzico(['paid_price' => 1.0]);
    $this->withToken($token)->postJson('/temp/api/v2/iyzico/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'token' => 'T-1'])
        ->assertJsonPath('result', false);
    $this->withToken($token)->postJson('/temp/api/v2/iyzico/success', ['payment_type' => 'cart_payment', 'combined_order_id' => $other->id, 'token' => 'T-2', 'user_id' => $other->user_id])
        ->assertJsonPath('result', false);

    expectOrderUnpaid($combined);
    expectOrderUnpaid($other);
});

test('API iyzico: a successful payment pays the signed-in customer\'s order once per token', function () {
    $customer = gatewayCustomer();
    $combined = unpaidGatewayOrder($customer, 30);
    fakeIyzico(['paid_price' => 30.0]);
    $request = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'payment_details' => json_encode(['token' => 'T-1'])];

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/iyzico/success', $request)->assertJsonPath('result', true);
    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/iyzico/success', $request)->assertJsonPath('result', false);

    expectOrderPaidOnce($combined);
});

test('API iyzico: a package is priced from the database, not from the request', function () {
    $customer = gatewayCustomer();
    $package = CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => 50, 'product_upload' => 10]);
    fakeIyzico(['paid_price' => 1.0]);

    $this->withToken(storeApiToken($customer))
        ->postJson('/temp/api/v2/iyzico/success', ['payment_type' => 'customer_package_payment', 'package_id' => $package->id, 'amount' => 1, 'token' => 'T-1'])
        ->assertJsonPath('result', false);

    expect($customer->fresh()->customer_package_id)->toBeNull();
});
