<?php

use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * PayHere was charged the store's own figure under whatever `PAYHERE_CURRENCY`
 * said, and a notification's amount was compared to the order total as it came,
 * with its currency never checked. The form now carries the amount converted to
 * the gateway's currency (signed with that currency), a currency the store has
 * no active rate for is refused, and a notification must name the currency the
 * store charges and match the converted amount due (an order, a package) or be
 * converted back to the store currency (a wallet top-up).
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['payhere'],
        'services.payhere.merchant_id' => 'MERCHANT-1',
        'services.payhere.secret' => 'real-secret',
    ]);
});

afterEach(function () {
    $_POST = [];
});

/** The store prices in EUR; PayHere charges in `$gateway` (a row per other currency: code => rate, status). */
function phCurrencies(?string $gateway = 'LKR', array $rates = ['LKR' => [320, 1]]): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    foreach ($rates as $code => [$rate, $status]) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }

    config(['services.payhere.currency' => $gateway]);
}

function phCustomer(): StoreUser
{
    return createStoreUser('customer');
}

function phOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

/** @return list<string> */
function phStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

/** A notification as PayHere signs it (md5 over merchant, order, amount, currency, status and the hashed secret). */
function phNotify(mixed $test, string $route, array $fields): void
{
    $post = $fields + ['merchant_id' => 'MERCHANT-1', 'payment_id' => 'PH-1', 'payhere_currency' => 'LKR', 'status_code' => '2'];
    $post['md5sig'] = strtoupper(md5($post['merchant_id'].$post['order_id'].$post['payhere_amount'].$post['payhere_currency'].$post['status_code'].strtoupper(md5('real-secret'))));
    $_POST = $post;

    $test->post(route($route));
}

/** @return array<string, string> the hidden and text inputs of the PayHere form, by name */
function phFormFields(string $html): array
{
    preg_match_all('/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => html_entity_decode($m[2])])->all();
}

function phExpectedHash(string $orderId, string $amount, string $currency): string
{
    return strtoupper(md5('MERCHANT-1'.$orderId.$amount.$currency.strtoupper(md5('real-secret'))));
}

// --- the signed form -----------------------------------------------------------

test('the form carries the converted amount and the gateway currency, signed with both', function () {
    phCurrencies();
    actingAsStoreUser(phCustomer());

    $response = $this->post(route('wallet.recharge'), ['amount' => 25, 'payment_option' => 'payhere'])->assertOk();
    $fields = phFormFields($response->getContent());

    expect($fields['currency'])->toBe('LKR')
        ->and($fields['amount'])->toBe('8000.00')
        ->and($fields['hash'])->toBe(phExpectedHash($fields['order_id'], '8000.00', 'LKR'));
});

test('with no gateway currency configured the form charges the store currency, exactly', function () {
    phCurrencies(null, []);
    actingAsStoreUser(phCustomer());

    $fields = phFormFields($this->post(route('wallet.recharge'), ['amount' => 24.6, 'payment_option' => 'payhere'])->getContent());

    expect($fields['currency'])->toBe('EUR')
        ->and($fields['amount'])->toBe('24.60')
        ->and($fields['hash'])->toBe(phExpectedHash($fields['order_id'], '24.60', 'EUR'));
});

test('a currency the store has no active rate for is refused and no form is shown', function (array $rates) {
    phCurrencies('LKR', $rates);
    actingAsStoreUser(phCustomer());

    $this->post(route('wallet.recharge'), ['amount' => 25, 'payment_option' => 'payhere'])->assertRedirect(route('cart'));
})->with([
    'no row' => [[]],
    'an inactive row' => [['LKR' => [320, 0]]],
    'a zero rate' => [['LKR' => [0, 1]]],
]);

// --- notifications -------------------------------------------------------------

test('a notification for the converted amount due pays the order once, in the store currency', function () {
    phCurrencies();
    $combined = phOrder(phCustomer(), 30);

    phNotify($this, 'payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => '9600.00']);
    phNotify($this, 'payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => '9600.00']);

    expect(phStatuses($combined))->toBe(['paid'])
        ->and(GatewayPayment::query()->where('reference', 'PH-1')->count())->toBe(1)
        ->and((float) GatewayPayment::query()->where('reference', 'PH-1')->value('amount'))->toBe(30.0);
});

test('a notification that is not the converted amount due, or not in the store\'s charge currency, pays nothing', function (string $amount, string $currency) {
    phCurrencies();
    $combined = phOrder(phCustomer(), 30);

    phNotify($this, 'payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => $amount, 'payhere_currency' => $currency]);

    expect(phStatuses($combined))->toBe(['unpaid'])
        ->and(GatewayPayment::count())->toBe(0);
})->with([
    'the unconverted 30.00' => ['30.00', 'LKR'],
    'a short 9000.00' => ['9000.00', 'LKR'],
    'the right figure in another currency' => ['9600.00', 'USD'],
]);

test('a wallet top-up notification credits the amount paid, converted back to the store currency, once', function () {
    phCurrencies();
    $customer = phCustomer();

    phNotify($this, 'payhere.wallet.notify', ['order_id' => 'W'.$customer->id.'-abcdefghij', 'payhere_amount' => '3200.00']);
    phNotify($this, 'payhere.wallet.notify', ['order_id' => 'W'.$customer->id.'-abcdefghij', 'payhere_amount' => '3200.00']);

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(10.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('a wallet top-up in another currency credits nothing', function () {
    phCurrencies();
    $customer = phCustomer();

    phNotify($this, 'payhere.wallet.notify', ['order_id' => 'W'.$customer->id.'-abcdefghij', 'payhere_amount' => '3200.00', 'payhere_currency' => 'USD']);

    expect(Wallet::query()->where('user_id', $customer->id)->count())->toBe(0);
});

test('a package notification pays the package once when it is the converted price', function () {
    phCurrencies();
    $customer = phCustomer();
    $package = CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => 50, 'product_upload' => 10]);
    $order = 'P'.$customer->id.'-'.$package->id.'-abcdefghij';

    phNotify($this, 'payhere.customer_package_payment.notify', ['order_id' => $order, 'payhere_amount' => '50.00']);
    expect(GatewayPayment::count())->toBe(0);

    phNotify($this, 'payhere.customer_package_payment.notify', ['order_id' => $order, 'payhere_amount' => '16000.00', 'payment_id' => 'PH-2']);
    phNotify($this, 'payhere.customer_package_payment.notify', ['order_id' => $order, 'payhere_amount' => '16000.00', 'payment_id' => 'PH-2']);

    expect(GatewayPayment::query()->where('reference', 'PH-2')->count())->toBe(1);
});

test('a store whose gateway currency is its own still accepts the exact amount', function () {
    phCurrencies(null, []);
    $combined = phOrder(phCustomer(), 24.6);

    phNotify($this, 'payhere.checkout.notify', ['order_id' => (string) $combined->id, 'payhere_amount' => '24.60', 'payhere_currency' => 'EUR']);

    expect(phStatuses($combined))->toBe(['paid']);
});
