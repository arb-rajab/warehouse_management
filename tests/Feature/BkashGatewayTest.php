<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * bKash was charged the store's figure rounded to a whole unit under a hard-coded
 * BDT, its completed payment's currency was never checked, a bKash that gave no
 * payment page sent the customer back to the cart with no word, and the mobile
 * API dereferenced null on a missing payment URL. The amount due is now converted
 * to the gateway currency to the cent, the charge currency is sent and verified,
 * and a missing payment page is refused with a message.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['bkash'],
        'services.bkash.currency' => 'BDT',
        'services.bkash.username' => 'u',
        'services.bkash.password' => 'p',
        'services.bkash.app_key' => 'k',
        'services.bkash.app_secret' => 's',
    ]);
    Http::preventStrayRequests();
    Sleep::fake();
});

/** The store prices in EUR; bKash charges in `$code` at `$rate` (null: no row for it). */
function bkCurrencies(?float $rate = 130.5, int $status = 1, string $code = 'BDT'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

function bkCustomer(): StoreUser
{
    return actingAsStoreUser(createStoreUser('customer'));
}

function bkOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

/** @return list<string> */
function bkStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

/** @param array<string, mixed> $execute what bKash's execute call answers */
function bkServer(array $execute = [], ?string $url = 'https://pay.bka.test/page'): void
{
    // A fresh client each time: Http::fake() keeps earlier stubs, and the first matching one wins.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*checkout/token/grant' => Http::response(['id_token' => 'TOKEN']),
        '*checkout/create' => $url === null ? Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid app key']) : Http::response(['paymentID' => 'PID', 'bkashURL' => $url]),
        '*checkout/execute' => Http::response($execute),
        '*checkout/payment/status' => Http::response($execute),
    ]);
}

function bkCompleted(string $amount, string $currency = 'BDT'): array
{
    return ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => $amount, 'currency' => $currency];
}

/** @return array<string, mixed> the body bKash was sent to create the payment */
function bkSentCreate(): array
{
    $sent = [];
    Http::assertSent(function (ClientRequest $request) use (&$sent) {
        if (str_ends_with($request->url(), 'checkout/create')) {
            $sent = $request->data();
        }

        return true;
    });

    return $sent;
}

// --- starting a payment (web) ----------------------------------------------------

test('the amount due is converted to the gateway currency, to the cent, and sent with that currency', function () {
    bkCurrencies(130.5);
    bkCustomer();
    bkServer();

    $this->post(route('wallet.recharge'), ['amount' => 24.6, 'payment_option' => 'bkash'])->assertRedirect(route('bkash.create_payment'));
    expect(session('amount'))->toBe('3210.30');

    $this->get(route('bkash.create_payment'))->assertRedirect('https://pay.bka.test/page');

    expect(bkSentCreate())->toMatchArray(['amount' => '3210.30', 'currency' => 'BDT', 'callbackURL' => route('bkash.callback')])
        ->and(session('bkash_payment_id'))->toBe('PID');
});

test('a gateway currency equal to the store currency is charged exactly, never rounded to a whole unit', function () {
    bkCurrencies(null);
    config(['services.bkash.currency' => 'EUR']);
    bkCustomer();
    bkServer();

    $this->post(route('wallet.recharge'), ['amount' => 24.6, 'payment_option' => 'bkash']);
    $this->get(route('bkash.create_payment'));

    expect(bkSentCreate())->toMatchArray(['amount' => '24.60', 'currency' => 'EUR']);
});

test('a currency the store has no active rate for is refused and bKash is not called', function (?float $rate, int $status) {
    bkCurrencies($rate, $status);
    bkCustomer();

    $this->post(route('wallet.recharge'), ['amount' => 25, 'payment_option' => 'bkash'])->assertRedirect(route('cart'));

    Http::assertNothingSent();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [130.5, 0],
    'a zero rate' => [0.0, 1],
]);

test('a bKash that gives no payment page is refused with a message and no payment is kept', function () {
    bkCurrencies(130.5);
    bkCustomer();
    bkServer(url: null);

    $this->post(route('wallet.recharge'), ['amount' => 25, 'payment_option' => 'bkash']);
    $this->get(route('bkash.create_payment'))->assertRedirect(route('cart'));

    expect(session('bkash_payment_id'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull();
});

// --- the callback (web) ----------------------------------------------------------

test('a payment completed for the converted amount due pays the order once, in the store currency', function () {
    bkCurrencies(130.5);
    $combined = bkOrder(bkCustomer(), 30);
    bkServer(bkCompleted('3915.00'));
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'bkash_payment_id' => 'PID', 'bkash_token' => 'TOKEN'];

    $this->withSession($session)->get(route('bkash.callback', ['paymentID' => 'PID']))->assertRedirect(route('bkash.success'));
    $this->get(route('bkash.success'));
    $this->get(route('bkash.success'));

    expect(bkStatuses($combined))->toBe(['paid']);
});

test('a completed payment that is not the converted amount due, or not in the charge currency, pays nothing', function (string $amount, string $currency) {
    bkCurrencies(130.5);
    $combined = bkOrder(bkCustomer(), 30);
    bkServer(bkCompleted($amount, $currency));

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'bkash_payment_id' => 'PID', 'bkash_token' => 'TOKEN'])
        ->get(route('bkash.callback', ['paymentID' => 'PID']))->assertOk();
    $this->get(route('bkash.success'));

    expect(bkStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => ['30.00', 'BDT'],
    'a short 3000.00' => ['3000.00', 'BDT'],
    'the right figure in another currency' => ['3915.00', 'USD'],
]);

test('a wallet top-up is credited the amount due in the store currency, once', function () {
    bkCurrencies(130.5);
    $customer = bkCustomer();
    bkServer(bkCompleted('3262.50'));
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'bkash_payment_id' => 'PID', 'bkash_token' => 'TOKEN'];

    $this->withSession($session)->get(route('bkash.callback', ['paymentID' => 'PID']))->assertRedirect(route('bkash.success'));
    $this->get(route('bkash.success'));
    $this->get(route('bkash.success'));

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('a store whose gateway currency is its own still verifies the exact amount', function () {
    bkCurrencies(null);
    config(['services.bkash.currency' => 'EUR']);
    $combined = bkOrder(bkCustomer(), 24.6);
    bkServer(bkCompleted('24.60', 'EUR'));

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'bkash_payment_id' => 'PID', 'bkash_token' => 'TOKEN'])
        ->get(route('bkash.callback', ['paymentID' => 'PID']))->assertRedirect(route('bkash.success'));
    $this->get(route('bkash.success'));

    expect(bkStatuses($combined))->toBe(['paid']);
});

// --- the mobile API --------------------------------------------------------------

test('the API sends the app to a page that charges the converted amount', function () {
    bkCurrencies(130.5);
    bkServer();

    $response = $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/api/v1/store/bkash/begin?payment_type=wallet_payment&amount=25')->assertJsonPath('result', true);

    expect($response->json('url'))->toBe(route('api.bkash.webpage', ['token' => 'TOKEN', 'amount' => '3262.50']));
});

test('the API refuses a currency with no active rate', function () {
    bkCurrencies(null);

    $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/api/v1/store/bkash/begin?payment_type=wallet_payment&amount=25')->assertJsonPath('result', false);

    Http::assertNothingSent();
});

test('the API credits a converted payment once, and refuses the unconverted figure or another currency', function () {
    bkCurrencies(130.5);
    $customer = createStoreUser('customer');
    $combined = bkOrder($customer, 30);
    $request = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'payment_id' => 'PID-1'];

    bkServer(bkCompleted('30.00'));
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', false);

    bkServer(bkCompleted('3915.00', 'USD'));
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', false);
    expect(bkStatuses($combined))->toBe(['unpaid']);

    bkServer(bkCompleted('3915.00'));
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', true);
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', false);

    expect(bkStatuses($combined))->toBe(['paid']);
});
