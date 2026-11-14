<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Utility\NgeniusUtility;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * N-Genius was wired to its UAT test environment only (no way to go live), always
 * charged in `NGENIUS_CURRENCY` with the store's own figure, never checked the
 * currency of a captured order, and assumed N-Genius always answered with a
 * payment page. It now has a UAT/live switch, converts the amount due to the
 * gateway currency in minor units, verifies the captured order's currency and
 * amount against the converted amount due, and refuses cleanly when N-Genius
 * gives no payment page.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['ngenius'],
        'services.ngenius.api_key' => 'key',
        'services.ngenius.outlet_id' => 'OUTLET-1',
        'services.ngenius.mode' => 'uat',
        'services.ngenius.currency' => 'AED',
    ]);
    Http::preventStrayRequests();
});

/** The store prices in EUR; N-Genius charges in `$code` at `$rate` (null: no row for it). */
function ngCurrencies(?float $rate = 4.0, int $status = 1, string $code = 'AED'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

function ngCustomer(): StoreUser
{
    return actingAsStoreUser(createStoreUser('customer'));
}

function ngFakeCreate(string $reference = 'REF-1', ?string $link = 'https://pay.ngenius.test/page/REF-1'): void
{
    Http::fake([
        '*identity*' => Http::response(['access_token' => 'ACCESS']),
        '*transactions/outlets/*/orders' => $link === null ? Http::response([], 500) : Http::response(['reference' => $reference, '_links' => ['payment' => ['href' => $link]]]),
    ]);
}

/** @return array<string, mixed> the order-creation body N-Genius was sent */
function ngSentOrder(): array
{
    $sent = null;
    Http::assertSent(function (ClientRequest $request) use (&$sent) {
        if (str_contains($request->url(), '/transactions/outlets/') && $request->method() === 'POST') {
            $sent = json_decode($request->body(), true);
        }

        return true;
    });

    return $sent ?? [];
}

function ngTopUp(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'ngenius']);
}

// --- starting a payment --------------------------------------------------------

test('the amount due is converted to the gateway currency and sent in minor units to the payment page', function () {
    ngCurrencies(4.0);
    ngCustomer();
    ngFakeCreate();

    ngTopUp($this, 25)->assertRedirect('https://pay.ngenius.test/page/REF-1');

    $order = ngSentOrder();
    expect($order['amount'])->toBe(['currencyCode' => 'AED', 'value' => 10000])
        ->and($order['merchantAttributes']['redirectUrl'])->toBe(route('ngenius.wallet_payment_callback'))
        ->and(session('ngenius_order_ref'))->toBe('REF-1');
});

test('a gateway currency equal to the store currency is charged exactly', function () {
    ngCurrencies(null);
    config(['services.ngenius.currency' => 'EUR']);
    ngCustomer();
    ngFakeCreate();

    ngTopUp($this, 24.6)->assertRedirect('https://pay.ngenius.test/page/REF-1');

    expect(ngSentOrder()['amount'])->toBe(['currencyCode' => 'EUR', 'value' => 2460]);
});

test('a currency the store has no active rate for is refused and N-Genius is not called', function (?float $rate, int $status) {
    ngCurrencies($rate, $status);
    ngCustomer();

    ngTopUp($this, 25)->assertRedirect(route('cart'));

    Http::assertNothingSent();
    expect(session('ngenius_order_ref'))->toBeNull();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [4.0, 0],
    'a zero rate' => [0.0, 1],
]);

test('an N-Genius that gives no payment page is refused with a message, and nothing is recorded', function () {
    ngCurrencies(4.0);
    ngCustomer();
    ngFakeCreate(link: null);

    ngTopUp($this, 25)->assertRedirect(route('cart'));

    expect(session('ngenius_order_ref'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull();
});

test('a payment with nothing due is refused', function () {
    ngCurrencies(4.0);
    ngCustomer();

    ngTopUp($this, 0)->assertRedirect(route('cart'));

    Http::assertNothingSent();
});

// --- the environment switch ----------------------------------------------------

test('UAT is the default and live is chosen only by NGENIUS_MODE=live', function (?string $mode, string $expected) {
    config(['services.ngenius.mode' => $mode]);

    expect(NgeniusUtility::getMode())->toBe($expected);
})->with([
    'unset' => [null, 'uat'],
    'uat' => ['uat', 'uat'],
    'live' => ['live', 'live'],
    'live in capitals' => ['LIVE', 'live'],
    'a typo is not live' => ['production', 'uat'],
]);

test('UAT talks to the UAT hosts and live to the production hosts', function () {
    ngCurrencies(4.0);
    ngCustomer();

    foreach (['uat' => ['identity-uat.ngenius-payments.com', 'api-gateway-uat.ngenius-payments.com'], 'live' => ['identity.ngenius-payments.com', 'api-gateway.ngenius-payments.com']] as $mode => [$identity, $gateway]) {
        config(['services.ngenius.mode' => $mode]);
        ngFakeCreate();

        ngTopUp($this, 25);

        Http::assertSent(fn (ClientRequest $request) => parse_url($request->url(), PHP_URL_HOST) === $identity);
        Http::assertSent(fn (ClientRequest $request) => parse_url($request->url(), PHP_URL_HOST) === $gateway);
    }
});

test('the environment URLs can be overridden in configuration', function () {
    config(['services.ngenius.mode' => 'live', 'services.ngenius.urls.live.gateway' => 'https://gateway.override.test']);

    expect(NgeniusUtility::getUrl('gateway'))->toBe('https://gateway.override.test');
});

// --- the callback --------------------------------------------------------------

function ngOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

function ngCaptured(string $reference, int $minor, string $currency = 'AED', string $state = 'CAPTURED'): void
{
    Http::fake([
        '*identity*' => Http::response(['access_token' => 'ACCESS']),
        '*transactions/outlets/*/orders/*' => Http::response([
            'reference' => $reference, 'amount' => ['value' => $minor, 'currencyCode' => $currency], '_embedded' => ['payment' => [['state' => $state]]],
        ]),
    ]);
}

/** @return list<string> */
function ngStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

test('an order captured for the converted amount due pays the order once, in the store currency', function () {
    ngCurrencies(4.0);
    $customer = ngCustomer();
    $combined = ngOrder($customer, 30);
    ngCaptured('REF-1', 12000);
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'ngenius_order_ref' => 'REF-1'];

    $this->withSession($session)->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']))->assertRedirect(route('order_confirmed'));
    $this->withSession($session)->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']));

    expect(ngStatuses($combined))->toBe(['paid']);
});

test('a captured order that is not the converted amount due, or not in the charge currency, pays nothing', function (int $minor, string $currency, string $state) {
    ngCurrencies(4.0);
    $customer = ngCustomer();
    $combined = ngOrder($customer, 30);
    ngCaptured('REF-1', $minor, $currency, $state);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'ngenius_order_ref' => 'REF-1'])
        ->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']));

    expect(ngStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => [3000, 'AED', 'CAPTURED'],
    'a short 110.00' => [11000, 'AED', 'CAPTURED'],
    'the right figure in another currency' => [12000, 'USD', 'CAPTURED'],
    'authorised but not captured' => [12000, 'AED', 'AUTHORISED'],
]);

test('a wallet top-up is credited in the store currency when the converted amount was captured', function () {
    ngCurrencies(4.0);
    $customer = ngCustomer();
    ngCaptured('REF-1', 10000);

    $this->withSession(['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'ngenius_order_ref' => 'REF-1'])
        ->get(route('ngenius.wallet_payment_callback', ['ref' => 'REF-1']));

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0);
});

test('a store whose gateway currency is its own still verifies the exact amount', function () {
    ngCurrencies(null);
    config(['services.ngenius.currency' => 'EUR']);
    $customer = ngCustomer();
    $combined = ngOrder($customer, 24.6);
    ngCaptured('REF-1', 2460, 'EUR');

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'ngenius_order_ref' => 'REF-1'])
        ->get(route('ngenius.cart_payment_callback', ['ref' => 'REF-1']));

    expect(ngStatuses($combined))->toBe(['paid']);
});
