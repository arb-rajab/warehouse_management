<?php

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Services\IyzicoPayments;
use Illuminate\Support\Facades\DB;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;

/**
 * iyzico was sent a made-up buyer ("John Doe", an address in Istanbul) for every
 * payment, every price rounded to a whole unit (24.60 was charged as 25), a
 * Turkish locale, and a callback that compared iyzico's amount to the order total
 * as it came with its currency never checked. The request now carries the
 * customer's own details and the exact amount, converted to iyzico's currency;
 * a currency with no active rate is refused, an iyzico that gives no payment page
 * is refused with a message, and the callback requires the charge currency and
 * the converted amount due.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['iyzico'],
        'services.iyzico.currency' => 'TRY',
        'services.iyzico.identity_number' => '11111111111',
    ]);
});

/** The store prices in EUR; iyzico charges in `$code` at `$rate` (null: no row for it). */
function izCurrencies(?float $rate = 35.0, int $status = 1, string $code = 'TRY'): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);

    if ($rate !== null) {
        DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => $status, 'code' => $code]);
    }
}

/** @param array<string, mixed> $attributes */
function izCustomer(array $attributes = []): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill($attributes + ['name' => 'Jan de Vries', 'phone' => '+31612345678', 'address' => 'Keizersgracht 1', 'city' => 'Amsterdam', 'country' => 'Netherlands', 'postal_code' => '1015AA'])->save();

    return $customer->fresh();
}

/**
 * Stand in for iyzico's server: the request builder runs for real, and what it built is kept.
 *
 * @param  object  $captured  gets a `request` property
 */
function izServer(object $captured, ?string $url = 'https://pay.iyzico.test/page'): void
{
    test()->partialMock(IyzicoPayments::class, function ($mock) use ($captured, $url) {
        $mock->shouldReceive('initialize')->andReturnUsing(function (CreatePayWithIyzicoInitializeRequest $request) use ($captured, $url) {
            $captured->request = $request;

            return $url;
        });
    });
}

function izTopUp(mixed $test, float $amount)
{
    return $test->post(route('wallet.recharge'), ['amount' => $amount, 'payment_option' => 'iyzico']);
}

// --- the request ---------------------------------------------------------------

test('the buyer and both addresses are the customer\'s own details', function () {
    izCurrencies();
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 25)->assertRedirect('https://pay.iyzico.test/page');

    $request = $captured->request;
    $buyer = $request->getBuyer();
    expect($buyer->getName())->toBe('Jan')
        ->and($buyer->getSurname())->toBe('de Vries')
        ->and($buyer->getGsmNumber())->toBe('+31612345678')
        ->and($buyer->getRegistrationAddress())->toBe('Keizersgracht 1')
        ->and($buyer->getCity())->toBe('Amsterdam')
        ->and($buyer->getCountry())->toBe('Netherlands')
        ->and($buyer->getZipCode())->toBe('1015AA')
        ->and($buyer->getIp())->toBe('127.0.0.1')
        ->and($buyer->getIdentityNumber())->toBe('11111111111');

    foreach ([$request->getShippingAddress(), $request->getBillingAddress()] as $address) {
        expect($address->getContactName())->toBe('Jan de Vries')
            ->and($address->getCity())->toBe('Amsterdam')
            ->and($address->getCountry())->toBe('Netherlands')
            ->and($address->getAddress())->toBe('Keizersgracht 1');
    }

    expect(json_encode($request->getJsonObject()))->not->toContain('John')->not->toContain('Istanbul')->not->toContain('Turkey');
});

test('a customer with no saved address still gets a valid request', function () {
    izCurrencies();
    actingAsStoreUser(izCustomer(['address' => null, 'city' => '', 'country' => null, 'phone' => null, 'postal_code' => null]));
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 25)->assertRedirect('https://pay.iyzico.test/page');

    expect($captured->request->getBuyer()->getRegistrationAddress())->toBe('-')
        ->and($captured->request->getBuyer()->getCity())->toBe('-')
        ->and($captured->request->getBuyer()->getGsmNumber())->toBeNull()
        ->and($captured->request->getBuyer()->getZipCode())->toBeNull();
});

test('the price is the converted amount to the cent, the same on the request, the paid price and the basket', function () {
    izCurrencies(35.0);
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 24.6);

    $request = $captured->request;
    expect($request->getCurrency())->toBe('TRY')
        ->and($request->getPrice())->toBe('861.00')
        ->and($request->getPaidPrice())->toBe('861.00')
        ->and($request->getBasketItems()[0]->getPrice())->toBe('861.00')
        ->and($request->getCallbackUrl())->toBe(route('iyzico.callback', ['payment_type' => 'wallet_payment']))
        ->and(session('iyzico_basket_id'))->toBe($request->getBasketId());
});

test('a charge in the store currency is exact, never rounded to a whole unit', function () {
    izCurrencies(null);
    config(['services.iyzico.currency' => 'EUR']);
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 24.6);

    expect($captured->request->getCurrency())->toBe('EUR')
        ->and($captured->request->getPrice())->toBe('24.60');
});

test('the locale is Turkish only on a Turkish storefront', function () {
    expect(app(IyzicoPayments::class)->locale())->toBe('en');

    app()->setLocale('tr');

    expect(app(IyzicoPayments::class)->locale())->toBe('tr');
});

test('a currency the store has no active rate for is refused and iyzico is not called', function (?float $rate, int $status) {
    izCurrencies($rate, $status);
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 25)->assertRedirect(route('cart'));

    expect($captured)->not->toHaveProperty('request')
        ->and(session('iyzico_basket_id'))->toBeNull();
})->with([
    'no row' => [null, 1],
    'an inactive row' => [35.0, 0],
    'a zero rate' => [0.0, 1],
]);

test('an iyzico that gives no payment page is refused with a message and no basket is kept', function () {
    izCurrencies();
    actingAsStoreUser(izCustomer());
    izServer(new stdClass, url: null);

    izTopUp($this, 25)->assertRedirect(route('cart'));

    expect(session('iyzico_basket_id'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull();
});

// --- the callback --------------------------------------------------------------

function izOrder(StoreUser $customer, float $total): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

/** @param array<string, mixed> $payment */
function izRetrieved(array $payment): void
{
    test()->mock(IyzicoPayments::class)->shouldReceive('retrieve')
        ->andReturn($payment + ['paid' => true, 'paid_price' => 0.0, 'currency' => 'TRY', 'basket_id' => 'BASKET-1', 'raw' => '{}']);
}

/** @return list<string> */
function izStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

test('a payment of the converted amount due pays the order once, in the store currency', function () {
    izCurrencies(35.0);
    $combined = izOrder(actingAsStoreUser(izCustomer()), 30);
    izRetrieved(['paid_price' => 1050.0]);
    $session = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'iyzico_basket_id' => 'BASKET-1'];

    $this->withSession($session)->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'T-1']);
    $this->withSession($session)->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'T-1']);

    expect(izStatuses($combined))->toBe(['paid']);
});

test('a payment that is not the converted amount due, or not in the charge currency, pays nothing', function (float $paid, string $currency) {
    izCurrencies(35.0);
    $combined = izOrder(actingAsStoreUser(izCustomer()), 30);
    izRetrieved(['paid_price' => $paid, 'currency' => $currency]);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'iyzico_basket_id' => 'BASKET-1'])
        ->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'T-1']);

    expect(izStatuses($combined))->toBe(['unpaid']);
})->with([
    'the unconverted 30.00' => [30.0, 'TRY'],
    'a short 1000.00' => [1000.0, 'TRY'],
    'the right figure in another currency' => [1050.0, 'EUR'],
]);

test('a wallet top-up is credited the amount due, in the store currency, once', function () {
    izCurrencies(35.0);
    $customer = actingAsStoreUser(izCustomer());
    izRetrieved(['paid_price' => 875.0]);
    $session = ['payment_type' => 'wallet_payment', 'payment_data' => ['amount' => 25], 'iyzico_basket_id' => 'BASKET-1'];

    $this->withSession($session)->post(route('iyzico.callback', ['payment_type' => 'wallet_payment']), ['token' => 'T-1']);
    $this->withSession($session)->post(route('iyzico.callback', ['payment_type' => 'wallet_payment']), ['token' => 'T-1']);

    expect((float) Wallet::query()->where('user_id', $customer->id)->sum('amount'))->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $customer->id)->count())->toBe(1);
});

test('a store whose gateway currency is its own still verifies the exact amount', function () {
    izCurrencies(null);
    config(['services.iyzico.currency' => 'EUR']);
    $combined = izOrder(actingAsStoreUser(izCustomer()), 24.6);
    izRetrieved(['paid_price' => 24.6, 'currency' => 'EUR']);

    $this->withSession(['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id, 'iyzico_basket_id' => 'BASKET-1'])
        ->post(route('iyzico.callback', ['payment_type' => 'cart_payment']), ['token' => 'T-1']);

    expect(izStatuses($combined))->toBe(['paid']);
});

// --- the mobile API ------------------------------------------------------------

test('the API starts a payment with the signed-in customer\'s own details and the converted amount', function () {
    izCurrencies(35.0);
    $customer = izCustomer();
    $captured = new stdClass;
    izServer($captured);

    $this->withToken(storeApiToken($customer))->get('/api/v1/store/iyzico/init?payment_type=wallet_payment&amount=25')->assertRedirect('https://pay.iyzico.test/page');

    $request = $captured->request;
    expect($request->getBuyer()->getName())->toBe('Jan')
        ->and($request->getBuyer()->getEmail())->toBe($customer->email)
        ->and($request->getCurrency())->toBe('TRY')
        ->and($request->getPrice())->toBe('875.00')
        ->and($request->getCallbackUrl())->toBe(route('api.iyzico.callback'));
});

test('the API refuses a currency with no active rate', function () {
    izCurrencies(null);
    $captured = new stdClass;
    izServer($captured);

    $this->withToken(storeApiToken(izCustomer()))->getJson('/api/v1/store/iyzico/init?payment_type=wallet_payment&amount=25')->assertJsonPath('result', false);

    expect($captured)->not->toHaveProperty('request');
});

// --- the identity number (IYZICO_IDENTITY_NUMBER, .env only) ------------------------

test('the buyer carries the configured identity number, and the config holds no placeholder default', function () {
    config(['services.iyzico.identity_number' => '12345678901']);
    izCurrencies();
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 25)->assertRedirect('https://pay.iyzico.test/page');

    expect($captured->request->getBuyer()->getIdentityNumber())->toBe('12345678901')
        ->and(file_get_contents(config_path('services.php')))->not->toContain('11111111111');
});

test('without IYZICO_IDENTITY_NUMBER the storefront refuses to start a payment and iyzico is not called', function (?string $number) {
    config(['services.iyzico.identity_number' => $number]);
    izCurrencies();
    actingAsStoreUser(izCustomer());
    $captured = new stdClass;
    izServer($captured);

    izTopUp($this, 25)->assertRedirect(route('cart'));

    expect($captured)->not->toHaveProperty('request')
        ->and(session('iyzico_basket_id'))->toBeNull()
        ->and(session('flash_notification'))->not->toBeNull()
        ->and(__('storefront.checkout.iyzico_unavailable'))->not->toBe('storefront.checkout.iyzico_unavailable')
        ->and(__('storefront.checkout.iyzico_unavailable', [], 'ar'))->not->toBe(__('storefront.checkout.iyzico_unavailable', [], 'en'));
})->with([
    'unset' => [null],
    'empty' => [''],
    'blank' => ['  '],
]);

test('without IYZICO_IDENTITY_NUMBER the API refuses to start a payment and says why', function () {
    config(['services.iyzico.identity_number' => null]);
    izCurrencies();
    $captured = new stdClass;
    izServer($captured);

    $this->withToken(storeApiToken(izCustomer()))->getJson('/api/v1/store/iyzico/init?payment_type=wallet_payment&amount=25')
        ->assertJsonPath('result', false)
        ->assertJsonPath('message', __('storefront.checkout.iyzico_unavailable'));

    expect($captured)->not->toHaveProperty('request');
});

test('the API credits a converted payment once, and refuses the unconverted figure or another currency', function () {
    izCurrencies(35.0);
    $customer = izCustomer();
    $combined = izOrder($customer, 30);
    $request = ['payment_type' => 'cart_payment', 'combined_order_id' => $combined->id];

    izRetrieved(['paid_price' => 30.0]);
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/iyzico/success', $request + ['token' => 'T-1'])->assertJsonPath('result', false);

    izRetrieved(['paid_price' => 1050.0, 'currency' => 'EUR']);
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/iyzico/success', $request + ['token' => 'T-2'])->assertJsonPath('result', false);
    expect(izStatuses($combined))->toBe(['unpaid']);

    izRetrieved(['paid_price' => 1050.0]);
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/iyzico/success', $request + ['token' => 'T-3'])->assertJsonPath('result', true);
    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/iyzico/success', $request + ['token' => 'T-3'])->assertJsonPath('result', false);

    expect(izStatuses($combined))->toBe(['paid']);
});
