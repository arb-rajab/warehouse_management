<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * aamarpay was charged the store's figure rounded to a whole unit under a fixed
 * BDT, posted to with raw curl (no timeout, verbose output on), echoed whatever it
 * answered unescaped into an auto-submitting form and ended the request with
 * exit, and compared the amount it reported to the order total as it came. The
 * amount due is now converted to the gateway currency to the cent, the request
 * goes through the HTTP client with a timeout, an answer that is not a payment
 * page is refused with a message, and the reported amount must be the converted
 * amount due.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['aamarpay'],
        'services.aamarpay.currency' => 'BDT',
        'services.aamarpay.store_id' => 'store-1',
        'services.aamarpay.signature_key' => 'sig-1',
    ]);
    Http::preventStrayRequests();
});

/** The store prices in EUR; aamarpay charges in `$code` at `$rate` (null: no row for it). */
function amCurrencies(?float $rate = 130.5, int $status = 1, string $code = 'BDT'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

function amCustomer(?string $phone = '+31612345678'): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['phone' => $phone])->save();

    return actingAsStoreUser($customer->fresh());
}

function amServer(string $body = '"paynow.php?track=AAM1"', int $status = 200): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*request.php' => Http::response($body, $status)]);
}

/** @return array<string, mixed> the form aamarpay was sent */
function amSent(): array
{
    $sent = [];
    Http::assertSent(function (ClientRequest $request) use (&$sent) {
        $sent = $request->data();

        return true;
    });

    return $sent;
}

function amTopUp(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'aamarpay']);
}

// --- starting a payment ----------------------------------------------------------

test('the amount due is converted to the gateway currency, to the cent, and sent with that currency', function () {
    amCurrencies(130.5);
    amCustomer();
    amServer();

    amTopUp($this, 24.6)->assertRedirect('https://secure.aamarpay.com/paynow.php?track=AAM1');

    $sent = amSent();
    expect($sent)->toMatchArray(['amount' => '3210.30', 'currency' => 'BDT', 'store_id' => 'store-1', 'signature_key' => 'sig-1', 'success_url' => route('aamarpay.success'), 'fail_url' => route('aamarpay.fail')])
        ->and(session('aamarpay_tran_id'))->toBe($sent['tran_id']);
});

test('the sandbox setting selects the sandbox host', function () {
    amCurrencies(130.5);
    DB::table('business_settings')->insert(['type' => 'aamarpay_sandbox', 'value' => '1']);
    amCustomer();
    amServer();

    amTopUp($this, 25)->assertRedirect('https://sandbox.aamarpay.com/paynow.php?track=AAM1');

    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://sandbox.aamarpay.com/request.php');
});

test('a gateway currency equal to the store currency is charged exactly, never rounded to a whole unit', function () {
    amCurrencies(null);
    config(['services.aamarpay.currency' => 'EUR']);
    amCustomer();
    amServer();

    amTopUp($this, 24.6);

    expect(amSent())->toMatchArray(['amount' => '24.60', 'currency' => 'EUR']);
});

test('a currency the store has no active rate for is refused and aamarpay is not called', function (?float $rate, int $status) {
    amCurrencies($rate, $status);
    amCustomer();

    amTopUp($this, 25)->assertRedirect(route('cart'));

    Http::assertNothingSent();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [130.5, 0],
    'a zero rate' => [0.0, 1],
]);

test('a customer with no phone number is asked for one before anything is sent', function () {
    amCurrencies(130.5);
    amCustomer(phone: null);

    amTopUp($this, 25)->assertRedirect(route('profile'));

    Http::assertNothingSent();
});

test('an answer that is not a payment page is refused with a message, and no transaction is kept', function (string $body, int $status) {
    amCurrencies(130.5);
    amCustomer();
    amServer($body, $status);

    amTopUp($this, 25)->assertRedirect(route('cart'));

    expect(session('aamarpay_tran_id'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull();
})->with([
    'an error status' => ['"paynow.php?track=AAM1"', 500],
    'a message, not a path' => ['Invalid signature key', 200],
    'markup' => ['"><script>alert(1)</script>', 200],
    'a path that climbs out' => ['"../../evil.php?x=1"', 200],
    'nothing' => ['', 200],
]);

// --- the callback ----------------------------------------------------------------

function amOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

function amSearchServer(string $amount, string $status = 'Successful', string $tranId = 'TRAN-1'): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*trxcheck/request.php*' => Http::response(['pay_status' => $status, 'mer_txnid' => $tranId, 'amount' => $amount])]);
}

/** @return list<string> */
function amStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

test('a payment of the converted amount due pays the order once, in the store currency', function () {
    amCurrencies(130.5);
    $combined = amOrder(amCustomer(), 30);
    amSearchServer('3915.00');
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'aamarpay_tran_id' => 'TRAN-1'];

    $this->withSession($session)->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);
    $this->withSession($session)->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);

    expect(amStatuses($combined))->toBe(['paid']);
});

test('a payment that is not the converted amount due, or not successful, pays nothing', function (string $amount, string $status) {
    amCurrencies(130.5);
    $combined = amOrder(amCustomer(), 30);
    amSearchServer($amount, $status);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'aamarpay_tran_id' => 'TRAN-1'])
        ->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);

    expect(amStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => ['30.00', 'Successful'],
    'a short 3000.00' => ['3000.00', 'Successful'],
    'the right figure, not paid' => ['3915.00', 'Failed'],
]);

test('a wallet top-up is credited the amount due in the store currency, once', function () {
    amCurrencies(130.5);
    $customer = amCustomer();
    amSearchServer('3262.50');
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'aamarpay_tran_id' => 'TRAN-1'];

    $this->withSession($session)->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);
    $this->withSession($session)->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('a store whose gateway currency is its own still verifies the exact amount', function () {
    amCurrencies(null);
    config(['services.aamarpay.currency' => 'EUR']);
    $combined = amOrder(amCustomer(), 24.6);
    amSearchServer('24.60');

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'aamarpay_tran_id' => 'TRAN-1'])
        ->post(route('aamarpay.success'), ['mer_txnid' => 'TRAN-1']);

    expect(amStatuses($combined))->toBe(['paid']);
});
