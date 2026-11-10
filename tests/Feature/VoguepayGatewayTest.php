<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * VoguePay's payment page could not have worked: it read the customer from a
 * `shipping_info` session value nothing sets (a 500), built its return URLs from
 * the site root without /temp, sent a FAILED wallet payment to the success URL,
 * showed the order total from the database without the payer's ownership check
 * in the store's currency whatever VoguePay charges in, and carried a
 * hard-coded third-party developer code. The page now takes the converted total,
 * the customer's own details and its return URLs from the controller, and the
 * callback requires the converted amount due.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['voguepay'],
        'services.voguepay.currency' => null,
        'services.voguepay.merchant_id' => 'MERCHANT-X',
    ]);
    Http::preventStrayRequests();
});

/** The store prices in EUR; VoguePay charges in `$code` at `$rate` when a row is given. */
function vgCurrencies(?float $rate = null, int $status = 1, string $code = 'NGN'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

/** @param array<string, mixed> $attributes */
function vgCustomer(array $attributes = []): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill($attributes + ['name' => 'Ada Obi', 'phone' => '+2348012345678', 'address' => '1 Marina', 'city' => 'Lagos', 'postal_code' => '100001'])->save();

    return actingAsStoreUser($customer->fresh());
}

function vgTopUp(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'voguepay']);
}

// --- the payment page ------------------------------------------------------------

test('the page carries the total in the store currency and the customer\'s own details', function () {
    vgCurrencies();
    vgCustomer();

    $html = vgTopUp($this, 24.6)->assertOk()->getContent();

    expect($html)->toContain("'MERCHANT-X'")
        ->and($html)->toContain("total: '24.60'")
        ->and($html)->toContain("cur: 'EUR'")
        ->and($html)->toContain("merchant_ref: '".session('voguepay_merchant_ref')."'")
        ->and($html)->toContain('Ada Obi')
        ->and($html)->toContain('Lagos')
        ->and($html)->not->toContain('developer_code');
});

test('a configured gateway currency converts the total', function () {
    vgCurrencies(1500.0);
    config(['services.voguepay.currency' => 'NGN']);
    vgCustomer();

    $html = vgTopUp($this, 24.6)->assertOk()->getContent();

    expect($html)->toContain("total: '36900.00'")->toContain("cur: 'NGN'");
});

test('the sandbox setting sends the demo merchant', function () {
    vgCurrencies();
    DB::table('business_settings')->insert(['type' => 'voguepay_sandbox', 'value' => '1']);
    vgCustomer();

    expect(vgTopUp($this, 25)->assertOk()->getContent())->toContain("'demo'")->not->toContain('MERCHANT-X');
});

test('the return URLs are the store\'s own routes under /temp, and a failed payment goes to the failure URL', function () {
    vgCurrencies();
    vgCustomer();

    $html = vgTopUp($this, 25)->getContent();

    expect($html)->toContain(str_replace('/', '\/', route('voguepay.success', ['id' => '__id__'])))
        ->and($html)->toContain(str_replace('/', '\/', route('voguepay.failure', ['id' => '__id__'])))
        ->and($html)->toContain('failedFunction = function (transaction_id) {'.PHP_EOL.'        location.href = failureUrl')
        ->and(route('voguepay.success', ['id' => 'X']))->toEndWith('/temp/vogue-pay/success/X');
});

test('a customer\'s details are escaped in the page script', function () {
    vgCurrencies();
    vgCustomer(['name' => 'Eve </script><script>alert(1)</script> O\'Neil']);

    $html = vgTopUp($this, 25)->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and($html)->toContain('alert(1)');
});

test('a customer with no saved details still gets a page', function () {
    vgCurrencies();
    vgCustomer(['address' => null, 'city' => null, 'postal_code' => null, 'phone' => null]);

    vgTopUp($this, 25)->assertOk();
});

test('a currency the store has no active rate for is refused', function (?float $rate, int $status) {
    vgCurrencies($rate, $status);
    config(['services.voguepay.currency' => 'NGN']);
    vgCustomer();

    vgTopUp($this, 25)->assertRedirect(route('cart'));

    expect(session('voguepay_merchant_ref'))->toBeNull();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [1500.0, 0],
    'a zero rate' => [0.0, 1],
]);

// --- the callback ----------------------------------------------------------------

function vgOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

function vgServer(string $total, string $ref = 'REF-1', string $id = 'TX-1', string $message = 'Approved'): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['voguepay.com*' => Http::response(['response_message' => $message, 'transaction_id' => $id, 'merchant_ref' => $ref, 'total' => $total])]);
}

/** @return list<string> */
function vgStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

test('a payment of the converted amount due pays the order once, in the store currency', function () {
    vgCurrencies(1500.0);
    config(['services.voguepay.currency' => 'NGN']);
    $combined = vgOrder(vgCustomer(), 30);
    vgServer('45000.00');
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'voguepay_merchant_ref' => 'REF-1'];

    $this->withSession($session)->get(route('voguepay.success', ['id' => 'TX-1']));
    $this->withSession($session)->get(route('voguepay.success', ['id' => 'TX-1']));

    expect(vgStatuses($combined))->toBe(['paid']);
});

test('a payment that is not the converted amount due pays nothing', function (string $total) {
    vgCurrencies(1500.0);
    config(['services.voguepay.currency' => 'NGN']);
    $combined = vgOrder(vgCustomer(), 30);
    vgServer($total);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'voguepay_merchant_ref' => 'REF-1'])
        ->get(route('voguepay.success', ['id' => 'TX-1']));

    expect(vgStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => ['30.00'],
    'a short 40000.00' => ['40000.00'],
]);

test('a wallet top-up is credited the amount due in the store currency, once', function () {
    vgCurrencies(1500.0);
    config(['services.voguepay.currency' => 'NGN']);
    $customer = vgCustomer();
    vgServer('37500.00');
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'voguepay_merchant_ref' => 'REF-1'];

    $this->withSession($session)->get(route('voguepay.success', ['id' => 'TX-1']));
    $this->withSession($session)->get(route('voguepay.success', ['id' => 'TX-1']));

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('a store whose gateway currency is its own still verifies the exact amount', function () {
    vgCurrencies();
    $combined = vgOrder(vgCustomer(), 24.6);
    vgServer('24.60');

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'voguepay_merchant_ref' => 'REF-1'])
        ->get(route('voguepay.success', ['id' => 'TX-1']));

    expect(vgStatuses($combined))->toBe(['paid']);
});
