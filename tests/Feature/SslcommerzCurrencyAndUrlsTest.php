<?php

use App\Http\Controllers\Api\V1\SslCommerzController as ApiSslCommerzController;
use App\Http\Controllers\SSLCommerz;
use App\Models\CombinedOrder;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * SSLCommerz sent its return URLs without the store's /temp prefix (a customer
 * returning from the gateway landed on a 404) and charged the store's own
 * figure in BDT whatever the store's currency (25.00 EUR was charged as 25.00
 * BDT). The return URLs now come from route(), and the amount is converted from
 * the store's currency to the gateway's, with the validation and the credit in
 * the matching units. A currency the store has no active rate for is refused.
 */
class FakeSslcommerzGateway extends SSLCommerz
{
    /** @var array<string, mixed>|null */
    public static ?array $sent = null;

    public function initiate($post_data, $get_pay_options = false)
    {
        self::$sent = $post_data;

        return [];
    }
}

class FakeApiSslCommerzGateway extends ApiSslCommerzController
{
    /** @var array<string, mixed>|null */
    public static ?array $sent = null;

    public function initiate($post_data)
    {
        self::$sent = $post_data;

        return response()->json(['result' => true, 'url' => 'https://gateway.test/pay', 'message' => 'ok']);
    }
}

beforeEach(function () {
    config(['store.enabled_payment_gateways' => ['sslcommerz']]);
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.sslcommerz.store_id' => 'store-1', 'services.sslcommerz.store_password' => 'store-pass']);
    FakeSslcommerzGateway::$sent = null;
    FakeApiSslCommerzGateway::$sent = null;
    $this->app->bind(SSLCommerz::class, FakeSslcommerzGateway::class);
    $this->app->bind(ApiSslCommerzController::class, FakeApiSslCommerzGateway::class);
});

/** The store prices in `$default`; `$rates` adds a row per other currency (code => rate, status). */
function sslcCurrencies(string $default = 'EUR', array $rates = []): void
{
    $id = DB::table('currencies')->insertGetId(['name' => $default, 'symbol' => $default, 'exchange_rate' => 1, 'status' => 1, 'code' => $default]);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    foreach ($rates as $code => [$rate, $status]) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

function sslcCustomer(): StoreUser
{
    return actingAsStoreUser(createStoreUser('customer'));
}

/** A top-up request as the wallet page posts it; it reaches the gateway controller's pay(). */
function sslcRecharge(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'sslcommerz']);
}

function sslcSignedPost(string $tranId, string $amount): array
{
    $post = ['tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount, 'status' => 'VALID'];
    $post['verify_key'] = implode(',', array_keys($post));
    $signed = array_intersect_key($post, array_flip(explode(',', $post['verify_key'])));
    $signed['store_passwd'] = md5('store-pass');
    ksort($signed);
    $post['verify_sign'] = md5(implode('&', array_map(fn ($key, $value) => "$key=$value", array_keys($signed), $signed)));

    return $post;
}

function sslcFakeValidation(string $tranId, string $amount, string $currency = 'BDT'): void
{
    Http::fake(['*sslcommerz.com/validator/*' => Http::response([
        'status' => 'VALID', 'tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount,
        'currency_type' => $currency, 'currency_amount' => $amount,
    ])]);
}

function sslcWalletTotal(StoreUser $customer): float
{
    return (float) Wallet::query()->where('user_id', $customer->id)->sum('amount');
}

// --- the return URLs -----------------------------------------------------------

test('the web return URLs carry the /temp prefix, so the customer lands on a store page', function () {
    sslcCurrencies('BDT');
    sslcCustomer();

    sslcRecharge($this, 25);

    expect(FakeSslcommerzGateway::$sent)->not->toBeNull()
        ->and(FakeSslcommerzGateway::$sent['success_url'])->toEndWith('/temp/sslcommerz/success')
        ->and(FakeSslcommerzGateway::$sent['fail_url'])->toEndWith('/temp/sslcommerz/fail')
        ->and(FakeSslcommerzGateway::$sent['cancel_url'])->toEndWith('/temp/sslcommerz/cancel');

    // And each of them is a real route of the store, not the 404 the unprefixed path was.
    foreach (['success', 'fail', 'cancel'] as $callback) {
        expect(app('router')->getRoutes()->match(Request::create(parse_url(FakeSslcommerzGateway::$sent["{$callback}_url"], PHP_URL_PATH), 'POST'))->getName())->toBe("sslcommerz.$callback");
    }
});

test('the API return URLs name the API routes under /api/v1/store', function () {
    sslcCurrencies('BDT');
    $customer = createStoreUser('customer');
    $order = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $order->id, 'grand_total' => 30]);
    $query = '?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $order->id, 'user_id' => $customer->id]);

    $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/sslcommerz/begin'.$query)->assertJsonPath('result', true);

    expect(FakeApiSslCommerzGateway::$sent['success_url'])->toEndWith('/api/v1/store/sslcommerz/success')
        ->and(FakeApiSslCommerzGateway::$sent['fail_url'])->toEndWith('/api/v1/store/sslcommerz/fail')
        ->and(FakeApiSslCommerzGateway::$sent['cancel_url'])->toEndWith('/api/v1/store/sslcommerz/cancel');
});

// --- the amount and currency ---------------------------------------------------

test('a store in euros is charged the amount converted to the gateway currency', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    sslcCustomer();

    sslcRecharge($this, 25);

    expect(FakeSslcommerzGateway::$sent['currency'])->toBe('BDT')
        ->and((float) FakeSslcommerzGateway::$sent['total_amount'])->toBe(3262.5);
});

test('a store already in the gateway currency is charged its own figure', function () {
    sslcCurrencies('BDT');
    sslcCustomer();

    sslcRecharge($this, 25);

    expect(FakeSslcommerzGateway::$sent['currency'])->toBe('BDT')
        ->and((float) FakeSslcommerzGateway::$sent['total_amount'])->toBe(25.0);
});

test('the gateway currency is configurable', function () {
    sslcCurrencies('EUR', ['USD' => [1.1, 1], 'BDT' => [130.5, 1]]);
    config(['services.sslcommerz.currency' => 'usd']);
    sslcCustomer();

    sslcRecharge($this, 20);

    expect(FakeSslcommerzGateway::$sent['currency'])->toBe('USD')
        ->and((float) FakeSslcommerzGateway::$sent['total_amount'])->toBe(22.0);
});

test('a currency the store has no active rate for is refused, and nothing is sent to the gateway', function (array $rates) {
    sslcCurrencies('EUR', $rates);
    sslcCustomer();

    sslcRecharge($this, 25)->assertRedirect(route('cart'));

    expect(FakeSslcommerzGateway::$sent)->toBeNull();
})->with([
    'no row at all' => [[]],
    'an inactive row' => [['BDT' => [130.5, 0]]],
    'a zero rate' => [['BDT' => [0, 1]]],
]);

test('the API refuses a currency with no active rate and records no pending payment', function () {
    sslcCurrencies('EUR');
    $customer = createStoreUser('customer');
    $order = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $order->id, 'grand_total' => 30]);
    $query = '?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $order->id, 'user_id' => $customer->id]);

    $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/sslcommerz/begin'.$query)->assertJsonPath('result', false);

    expect(FakeApiSslCommerzGateway::$sent)->toBeNull()
        ->and(GatewayPayment::count())->toBe(0);
});

test('the API charges the converted amount and records the pending payment in the store currency', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $customer = createStoreUser('customer');
    $order = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $order->id, 'grand_total' => 30]);
    $query = '?'.http_build_query(['payment_type' => 'cart_payment', 'combined_order_id' => $order->id, 'user_id' => $customer->id]);

    $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/sslcommerz/begin'.$query)->assertJsonPath('result', true);

    expect(FakeApiSslCommerzGateway::$sent['currency'])->toBe('BDT')
        ->and((float) FakeApiSslCommerzGateway::$sent['total_amount'])->toBe(3915.0)
        ->and((float) GatewayPayment::query()->value('amount'))->toBe(30.0);
});

// --- verifying and crediting ---------------------------------------------------

test('a payment confirmed in the gateway currency credits the amount due in the store currency, once', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $customer = sslcCustomer();
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'sslcommerz_tran_id' => 'T-1'];
    sslcFakeValidation('T-1', '3262.50');

    $this->withSession($session)->post(route('sslcommerz.success'), sslcSignedPost('T-1', '3262.50'));
    $this->withSession($session)->post(route('sslcommerz.success'), sslcSignedPost('T-1', '3262.50'));

    expect(sslcWalletTotal($customer))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('the store-currency figure, or a short amount, is not accepted as the payment of a converted charge', function (string $confirmed) {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $customer = sslcCustomer();
    sslcFakeValidation('T-1', $confirmed);

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'sslcommerz_tran_id' => 'T-1'])
        ->post(route('sslcommerz.success'), sslcSignedPost('T-1', $confirmed));

    expect(sslcWalletTotal($customer))->toBe(0.0);
})->with([
    'the unconverted 25.00' => '25.00',
    'a short 3000.00' => '3000.00',
]);

test('a store with no rate for the gateway currency pays nothing on a callback', function () {
    sslcCurrencies('EUR');
    $customer = sslcCustomer();
    sslcFakeValidation('T-1', '25.00');

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'sslcommerz_tran_id' => 'T-1'])
        ->post(route('sslcommerz.success'), sslcSignedPost('T-1', '25.00'));

    expect(sslcWalletTotal($customer))->toBe(0.0);
});

test('a store already in the gateway currency still credits what the gateway confirmed', function () {
    sslcCurrencies('BDT');
    $customer = sslcCustomer();
    sslcFakeValidation('T-1', '25.00');

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'sslcommerz_tran_id' => 'T-1'])
        ->post(route('sslcommerz.success'), sslcSignedPost('T-1', '25.00'));

    expect(sslcWalletTotal($customer))->toBe(25.0);
});

test('the API credits a converted payment once, in the store currency', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $customer = createStoreUser('customer');
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => 30]);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    sslcFakeValidation('AIZ-1', '3915.00');

    $this->post(route('api.sslcommerz.success'), sslcSignedPost('AIZ-1', '3915.00'))->assertJsonPath('result', true);
    $this->post(route('api.sslcommerz.success'), sslcSignedPost('AIZ-1', '3915.00'))->assertJsonPath('result', false);

    expect(Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->all())->toBe(['paid']);
});

test('the API refuses a post confirming the unconverted figure for a converted charge', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $customer = createStoreUser('customer');
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => 30]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => 30]);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    sslcFakeValidation('AIZ-1', '30.00');

    $this->post(route('api.sslcommerz.success'), sslcSignedPost('AIZ-1', '30.00'))->assertJsonPath('result', false);

    expect(Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->all())->toBe(['unpaid']);
});

// --- the conversion service ----------------------------------------------------

test('GatewayCurrency converts by the store rule: amount / default rate * gateway rate', function () {
    $default = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 2, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $default]);
    DB::table('currencies')->insert(['name' => 'Taka', 'symbol' => '৳', 'exchange_rate' => 260, 'status' => 1, 'code' => 'BDT']);
    // A newer inactive row and an older active one: only an active row counts.
    DB::table('currencies')->insert(['name' => 'Taka old', 'symbol' => '৳', 'exchange_rate' => 999, 'status' => 0, 'code' => 'BDT']);

    expect((new GatewayCurrency('BDT'))->fromStore(10.0))->toBe(1300.0)
        ->and((new GatewayCurrency('EUR'))->fromStore(10.456))->toBe(10.46)
        ->and((new GatewayCurrency('EUR'))->isStoreCurrency())->toBeTrue()
        ->and((new GatewayCurrency('BDT'))->isStoreCurrency())->toBeFalse()
        ->and((new GatewayCurrency('BDT'))->paidInStoreCurrency(1300.0, 10.0))->toBe(10.0)
        ->and((new GatewayCurrency('EUR'))->paidInStoreCurrency(9.5, 10.0))->toBe(9.5);
});

test('GatewayCurrency treats a store with no default currency as already in the gateway currency', function () {
    expect((new GatewayCurrency('BDT'))->isStoreCurrency())->toBeTrue()
        ->and((new GatewayCurrency('BDT'))->fromStore(25.0))->toBe(25.0);
});

test('GatewayCurrency reads each gateway\'s currency from its own config, and empty means the store currency', function () {
    sslcCurrencies('EUR', ['LKR' => [320, 1]]);
    config(['services.payhere.currency' => 'lkr', 'services.voguepay.currency' => null]);

    expect(GatewayCurrency::forGateway('payhere')->code)->toBe('LKR')
        ->and(GatewayCurrency::forGateway('payhere')->chargeCode())->toBe('LKR')
        ->and(GatewayCurrency::forGateway('voguepay')->code)->toBe('')
        ->and(GatewayCurrency::forGateway('voguepay')->chargeCode())->toBe('EUR')
        ->and(GatewayCurrency::forGateway('voguepay')->isStoreCurrency())->toBeTrue();
});

test('GatewayCurrency converts back to the store currency and matches an amount due to the cent', function () {
    sslcCurrencies('EUR', ['BDT' => [130.5, 1]]);
    $bdt = new GatewayCurrency('BDT');

    expect($bdt->toStore(3262.5))->toBe(25.0)
        ->and($bdt->matchesDue(3262.5, 25.0))->toBeTrue()
        ->and($bdt->matchesDue(3262.51, 25.0))->toBeTrue()
        ->and($bdt->matchesDue(3262.49, 25.0))->toBeTrue()
        ->and($bdt->matchesDue(3262.0, 25.0))->toBeFalse()
        ->and($bdt->matchesDue(25.0, 25.0))->toBeFalse()
        ->and((new GatewayCurrency('USD'))->toStore(10.0))->toBeNull()
        ->and((new GatewayCurrency('USD'))->matchesDue(10.0, 10.0))->toBeFalse();
});

test('GatewayCurrency formats amounts the way gateways expect them', function () {
    expect(GatewayCurrency::format(24.6))->toBe('24.60')
        ->and(GatewayCurrency::format(1234567.891))->toBe('1234567.89')
        ->and(GatewayCurrency::format(25))->toBe('25.00');
});
