<?php

use App\Models\CombinedOrder;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Services\GatewayPaymentService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Nagad was charged the store's unconverted figure in BDT, printed Nagad's raw
 * answer or called dd() when it did not answer as expected, set PHP's default
 * timezone to Dhaka for the rest of the request (skewing every timestamp the app
 * wrote), and compared the amount it reported to the order total as it came. The
 * amount due is now converted to BDT to the cent, a Nagad that does not answer is
 * refused with a message, Nagad's Dhaka timestamps are made without touching
 * PHP's default, and the reported amount must be the converted amount due.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['nagad'],
        'services.nagad.currency' => 'BDT',
        'nagad.callback_url' => null,
        'nagad.merchant_id' => 'MERCHANT-1',
        'nagad.sandbox_mode' => 'sandbox',
    ]);
    Http::preventStrayRequests();
});

afterEach(function () {
    Carbon::setTestNow();
});

/** The store prices in EUR; Nagad charges in `$code` at `$rate` (null: no row for it). */
function ngdCurrencies(?float $rate = 130.5, int $status = 1, string $code = 'BDT'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

function ngdCustomer(): StoreUser
{
    return actingAsStoreUser(createStoreUser('customer'));
}

/** @return array{pg: OpenSSLAsymmetricKey, merchant: OpenSSLAsymmetricKey} the keys Nagad and the merchant hold */
function ngdKeys(): array
{
    $pg = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $merchant = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($merchant, $merchantPem);

    $body = fn (string $pem) => trim(preg_replace('/-----(BEGIN|END)[A-Z ]+-----/', '', $pem));
    config([
        'nagad.pg_public_key' => $body(openssl_pkey_get_details($pg)['key']),
        'nagad.merchant_private_key' => $body($merchantPem),
    ]);

    return ['pg' => $pg, 'merchant' => $merchant];
}

/**
 * Stand in for Nagad. `$state` collects what the store sent, decrypted with the key Nagad holds.
 *
 * @param  array{pg: OpenSSLAsymmetricKey, merchant: OpenSSLAsymmetricKey}  $keys
 * @param  array<string, mixed>|null  $complete  Nagad's answer to the complete call
 */
function ngdServer(object $state, array $keys, ?array $complete = ['status' => 'Success', 'callBackUrl' => 'https://nagad.test/pay'], bool $answersInitialize = true): void
{
    $decrypt = function (ClientRequest $request) use ($keys) {
        openssl_private_decrypt(base64_decode($request['sensitiveData']), $plain, $keys['pg']);

        return json_decode((string) $plain, true);
    };

    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*check-out/initialize/*' => function (ClientRequest $request) use ($state, $keys, $decrypt, $answersInitialize) {
            $state->initialize = $decrypt($request);
            $state->initializeUrl = $request->url();

            if (! $answersInitialize) {
                return Http::response([]);
            }

            openssl_public_encrypt(json_encode(['paymentReferenceId' => 'PREF-1', 'challenge' => 'CHALLENGE']), $cipher, openssl_pkey_get_details($keys['merchant'])['key']);

            return Http::response(['sensitiveData' => base64_encode($cipher), 'signature' => 'sig']);
        },
        '*check-out/complete/*' => function (ClientRequest $request) use ($state, $decrypt, $complete) {
            $state->complete = $decrypt($request);
            $state->completeBody = $request->data();

            return Http::response($complete ?? []);
        },
    ]);
}

function ngdTopUp(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'nagad']);
}

// --- starting a payment (web) ----------------------------------------------------

test('the amount due is converted to BDT to the cent and sent to Nagad with the BDT currency code', function () {
    ngdCurrencies(130.5);
    $keys = ngdKeys();
    ngdCustomer();
    ngdServer($state = new stdClass, $keys);

    ngdTopUp($this, 24.6)->assertRedirect('https://nagad.test/pay');

    expect($state->complete)->toMatchArray(['amount' => '3210.30', 'currencyCode' => '050', 'merchantId' => 'MERCHANT-1', 'challenge' => 'CHALLENGE'])
        ->and($state->completeBody['merchantCallbackURL'])->toBe(route('nagad.callback'))
        ->and(session('nagad_order_id'))->toBe($state->complete['orderId']);
});

test('Nagad is sent Dhaka time, and PHP\'s default timezone is left alone', function () {
    ngdCurrencies(130.5);
    $keys = ngdKeys();
    ngdCustomer();
    ngdServer($state = new stdClass, $keys);
    Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
    $zone = date_default_timezone_get();

    ngdTopUp($this, 25);

    expect($state->initialize['datetime'])->toBe('20261110180000')
        ->and(date_default_timezone_get())->toBe($zone);
});

test('Nagad charges in BDT only: another configured currency is refused', function () {
    ngdCurrencies(130.5);
    config(['services.nagad.currency' => 'EUR']);
    ngdCustomer();

    ngdTopUp($this, 25)->assertRedirect(route('cart'));

    Http::assertNothingSent();
});

test('a currency the store has no active rate for is refused and Nagad is not called', function (?float $rate, int $status) {
    ngdCurrencies($rate, $status);
    ngdKeys();
    ngdCustomer();

    ngdTopUp($this, 25)->assertRedirect(route('cart'));

    Http::assertNothingSent();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [130.5, 0],
    'a zero rate' => [0.0, 1],
]);

test('a Nagad that does not answer as expected is refused with a message, shows nothing raw, and keeps no order', function (bool $answersInitialize, ?array $complete) {
    ngdCurrencies(130.5);
    $keys = ngdKeys();
    ngdCustomer();
    ngdServer(new stdClass, $keys, $complete, $answersInitialize);

    $response = ngdTopUp($this, 25);

    $response->assertRedirect(route('cart'));
    expect(session('nagad_order_id'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull()
        ->and($response->getContent())->not->toContain('status');
})->with([
    'no answer to initialize' => [false, null],
    'a failed complete' => [true, ['status' => 'Failed', 'message' => 'Invalid']],
    'a complete with no payment page' => [true, ['status' => 'Success']],
]);

// --- the callback (web) ----------------------------------------------------------

function ngdOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

function ngdVerifyServer(array $result): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*verify/payment/*' => Http::response($result)]);
}

/** @return list<string> */
function ngdStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

test('a payment of the converted amount due pays the order once, in the store currency', function () {
    ngdCurrencies(130.5);
    $combined = ngdOrder(ngdCustomer(), 30);
    ngdVerifyServer(['status' => 'Success', 'orderId' => 'Inv-1', 'paymentRefId' => 'NREF-1', 'amount' => '3915.00']);
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'nagad_order_id' => 'Inv-1'];

    $this->withSession($session)->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1']));
    $this->withSession($session)->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1']));

    expect(ngdStatuses($combined))->toBe(['paid']);
});

test('a payment that is not the converted amount due pays nothing', function (string $amount) {
    ngdCurrencies(130.5);
    $combined = ngdOrder(ngdCustomer(), 30);
    ngdVerifyServer(['status' => 'Success', 'orderId' => 'Inv-1', 'paymentRefId' => 'NREF-1', 'amount' => $amount]);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'nagad_order_id' => 'Inv-1'])
        ->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1']));

    expect(ngdStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => ['30.00'],
    'a short 3000.00' => ['3000.00'],
]);

test('a wallet top-up is credited the amount due in the store currency, once', function () {
    ngdCurrencies(130.5);
    $customer = ngdCustomer();
    ngdVerifyServer(['status' => 'Success', 'orderId' => 'Inv-1', 'paymentRefId' => 'NREF-1', 'amount' => '3262.50']);
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'nagad_order_id' => 'Inv-1'];

    $this->withSession($session)->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1']));
    $this->withSession($session)->get(route('nagad.callback', ['payment_ref_id' => 'NREF-1']));

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

// --- the mobile API --------------------------------------------------------------

test('the API charges the converted amount and records the pending payment in the store currency', function () {
    ngdCurrencies(130.5);
    $keys = ngdKeys();
    ngdServer($state = new stdClass, $keys);

    $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/api/v1/store/nagad/begin?payment_type=wallet_payment&amount=25')
        ->assertJsonPath('result', true)
        ->assertJsonPath('url', 'https://nagad.test/pay');

    expect($state->complete)->toMatchArray(['amount' => '3262.50', 'currencyCode' => '050'])
        ->and((float) GatewayPayment::query()->value('amount'))->toBe(25.0);
});

test('the API refuses a currency with no active rate and records nothing', function () {
    ngdCurrencies(null);
    ngdKeys();

    $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/api/v1/store/nagad/begin?payment_type=wallet_payment&amount=25')->assertJsonPath('result', false);

    Http::assertNothingSent();
    expect(GatewayPayment::count())->toBe(0);
});

test('the API credits a converted payment once, and refuses the unconverted figure', function () {
    ngdCurrencies(130.5);
    $customer = createStoreUser('customer');
    $combined = ngdOrder($customer, 30);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-API-1', $customer->id, 'cart_payment', $combined->id, null, 30);
    $url = '/api/v1/store/nagad/verify/cart_payment?payment_ref_id=NREF-1';

    ngdVerifyServer(['status' => 'Success', 'orderId' => 'Inv-API-1', 'paymentRefId' => 'NREF-1', 'amount' => '30.00']);
    $this->getJson($url)->assertJsonPath('result', false);
    expect(ngdStatuses($combined))->toBe(['unpaid']);

    ngdVerifyServer(['status' => 'Success', 'orderId' => 'Inv-API-1', 'paymentRefId' => 'NREF-1', 'amount' => '3915.00']);
    $this->getJson($url)->assertJsonPath('result', true);
    $this->getJson($url)->assertJsonPath('result', false);

    expect(ngdStatuses($combined))->toBe(['paid']);
});
