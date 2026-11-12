<?php

use App\Models\CustomerPackage;
use App\Models\GatewayPayment;
use App\Models\StoreUser;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\VerifiedPayment;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Seller packages and customer packages bought through the payment gateways
 * (LegacyGatewayPaymentCreditingTest covers orders and wallets): the package is
 * priced from the database and converted to the gateway's currency, a gateway's
 * confirmation credits the signed-in payer only, a repeated callback credits
 * nothing, an amount that does not pay the converted price is refused, and the
 * package is activated only after a verified credit.
 *
 * The web has no page that starts a seller package payment (the seller package
 * pages are gone), so a seller package reaches the gateways through the mobile
 * API (`payment_type=seller_package_payment`, bKash, Nagad, SSLCommerz, iyzico)
 * and, on the web, through the callbacks of a session that holds one
 * (N-Genius here). Both package types are tested through every path that has one.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['bkash', 'iyzico', 'nagad', 'ngenius', 'sslcommerz'],
        'services.sslcommerz.store_id' => 'store-1',
        'services.sslcommerz.store_password' => 'store-pass',
        'services.ngenius.api_key' => 'key',
        'services.ngenius.outlet_id' => 'OUTLET-1',
        'services.ngenius.mode' => 'uat',
        'services.ngenius.currency' => 'AED',
        'services.iyzico.currency' => 'TRY',
    ]);
    Http::preventStrayRequests();
    Sleep::fake();
});

dataset('package types', [
    'seller package' => GatewayPaymentService::SELLER_PACKAGE,
    'customer package' => GatewayPaymentService::CUSTOMER_PACKAGE,
]);

/** The store prices in EUR; the gateway charges in `$code` at `$rate` per euro. */
function pkgCurrencies(string $code, float $rate): void
{
    $id = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $id]);
    DB::table('currencies')->insert(['name' => $code, 'symbol' => $code, 'exchange_rate' => $rate, 'status' => 1, 'code' => $code]);
}

/** A buyer of a `$type` package: a seller with a shop, or a customer. */
function pkgPayer(string $type): StoreUser
{
    $payer = createStoreUser($type === GatewayPaymentService::SELLER_PACKAGE ? 'seller' : 'customer');

    if ($type === GatewayPaymentService::SELLER_PACKAGE) {
        DB::table('shops')->insert(['user_id' => $payer->id, 'name' => 'Shop '.$payer->id, 'slug' => 'shop-'.$payer->id]);
    }

    return $payer;
}

/** A package of `$type` priced `$amount` in the store currency; returns its id. */
function pkgPackage(string $type, float $amount = 50): int
{
    if ($type === GatewayPaymentService::SELLER_PACKAGE) {
        return DB::table('seller_packages')->insertGetId(['name' => 'Gold', 'amount' => $amount, 'product_upload_limit' => 500, 'duration' => 30]);
    }

    return CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => $amount, 'product_upload' => 10])->id;
}

/** Whether `$payer` now holds the package: the shop's package and one payment row, or the customer's package. */
function pkgActivated(string $type, StoreUser $payer, int $packageId): bool
{
    if ($type === GatewayPaymentService::SELLER_PACKAGE) {
        return (int) DB::table('shops')->where('user_id', $payer->id)->value('seller_package_id') === $packageId
            && DB::table('seller_package_payments')->where('user_id', $payer->id)->where('seller_package_id', $packageId)->count() === 1;
    }

    return (int) $payer->fresh()->customer_package_id === $packageId;
}

/** Nothing was granted to `$payer`, and no payment is completed (a payment `begin()` recorded may still be pending). */
function pkgExpectNothing(string $type, StoreUser $payer): void
{
    if ($type === GatewayPaymentService::SELLER_PACKAGE) {
        expect(DB::table('shops')->where('user_id', $payer->id)->value('seller_package_id'))->toBeNull()
            ->and((int) DB::table('shops')->where('user_id', $payer->id)->value('product_upload_limit'))->toBe(0)
            ->and(DB::table('seller_package_payments')->where('user_id', $payer->id)->count())->toBe(0);
    } else {
        expect($payer->fresh()->customer_package_id)->toBeNull()
            ->and((int) $payer->fresh()->remaining_uploads)->toBe(0);
    }

    expect(GatewayPayment::query()->where('user_id', $payer->id)->whereNotNull('completed_at')->count())->toBe(0);
}

// --- the shared sink -----------------------------------------------------------

test('a verified payment activates the package for its payer once, and not before', function (string $type) {
    $payer = pkgPayer($type);
    $bystander = pkgPayer($type);
    $packageId = pkgPackage($type);
    $service = app(GatewayPaymentService::class);

    pkgExpectNothing($type, $payer);

    expect($service->complete(new VerifiedPayment('ngenius', 'REF-1', 50.0, '{}'), $payer->id, $type, null, $packageId))->toBeTrue()
        ->and($service->complete(new VerifiedPayment('ngenius', 'REF-1', 50.0, '{}'), $payer->id, $type, null, $packageId))->toBeFalse()
        ->and(pkgActivated($type, $payer, $packageId))->toBeTrue();
    pkgExpectNothing($type, $bystander);

    $row = GatewayPayment::query()->where('user_id', $payer->id)->sole();
    expect($row->only(['gateway', 'reference', 'payment_type', 'package_id', 'amount']))
        ->toEqual(['gateway' => 'ngenius', 'reference' => 'REF-1', 'payment_type' => $type, 'package_id' => $packageId, 'amount' => '50.00']);

    if ($type === GatewayPaymentService::CUSTOMER_PACKAGE) {
        expect((int) $payer->fresh()->remaining_uploads)->toBe(10);
    } else {
        expect((int) DB::table('shops')->where('user_id', $payer->id)->value('product_upload_limit'))->toBe(500)
            ->and(DB::table('seller_package_payments')->where('user_id', $payer->id)->value('approval'))->toBe(1);
    }
})->with('package types');

test('a payment that does not pay the package price activates nothing and records nothing', function (string $type) {
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type);
    $service = app(GatewayPaymentService::class);

    foreach ([49.99, 1.0, 0.0] as $short) {
        expect($service->complete(new VerifiedPayment('ngenius', 'REF-SHORT', $short, '{}'), $payer->id, $type, null, $packageId))->toBeFalse();
    }
    pkgExpectNothing($type, $payer);

    // The reference was not used up by the refused attempts.
    expect($service->complete(new VerifiedPayment('ngenius', 'REF-SHORT', 50.0, '{}'), $payer->id, $type, null, $packageId))->toBeTrue()
        ->and(pkgActivated($type, $payer, $packageId))->toBeTrue();
})->with('package types');

test('a package that does not exist or is free is not payable, and the price is the database\'s', function (string $type) {
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    $free = pkgPackage($type, 0);
    $service = app(GatewayPaymentService::class);

    expect($service->amountDue($type, $payer->id, null, $packageId))->toBe(50.0)
        ->and($service->amountDue($type, $payer->id, null, $free))->toBeNull()
        ->and($service->amountDue($type, $payer->id, null, 999999))->toBeNull()
        ->and($service->complete(new VerifiedPayment('ngenius', 'REF-X', 50.0, '{}'), $payer->id, $type, null, 999999))->toBeFalse();
    pkgExpectNothing($type, $payer);
})->with('package types');

test('an account without a shop is credited no seller package', function () {
    $customer = createStoreUser('customer');
    $packageId = pkgPackage(GatewayPaymentService::SELLER_PACKAGE);

    expect(app(GatewayPaymentService::class)->complete(new VerifiedPayment('ngenius', 'REF-1', 50.0, '{}'), $customer->id, GatewayPaymentService::SELLER_PACKAGE, null, $packageId))->toBeFalse()
        ->and(DB::table('seller_package_payments')->count())->toBe(0)
        ->and(GatewayPayment::count())->toBe(0);
});

test('a seller package starts today when none is active and extends an active one from its end date', function () {
    $payer = pkgPayer(GatewayPaymentService::SELLER_PACKAGE);
    $packageId = pkgPackage(GatewayPaymentService::SELLER_PACKAGE);
    $service = app(GatewayPaymentService::class);
    $pay = fn (string $reference) => $service->complete(new VerifiedPayment('ngenius', $reference, 50.0, '{}'), $payer->id, GatewayPaymentService::SELLER_PACKAGE, null, $packageId);
    $validUntil = fn () => (string) DB::table('shops')->where('user_id', $payer->id)->value('package_invalid_at');

    // Never bought: 30 days from today.
    expect($pay('REF-1'))->toBeTrue()
        ->and($validUntil())->toBe(now()->addDays(30)->toDateString());

    // Active: the next 30 days follow the end date.
    expect($pay('REF-2'))->toBeTrue()
        ->and($validUntil())->toBe(now()->addDays(60)->toDateString());

    // Expired a year ago: counted from today, so the renewed package is valid.
    DB::table('shops')->where('user_id', $payer->id)->update(['package_invalid_at' => now()->subYear()->toDateString()]);
    expect($pay('REF-3'))->toBeTrue()
        ->and($validUntil())->toBe(now()->addDays(30)->toDateString());
});

// --- N-Genius (web) ----------------------------------------------------------------

function pkgNgeniusCreated(): void
{
    Http::fake([
        '*identity*' => Http::response(['access_token' => 'ACCESS']),
        '*transactions/outlets/*/orders' => Http::response(['reference' => 'REF-1', '_links' => ['payment' => ['href' => 'https://pay.ngenius.test/page/REF-1']]]),
    ]);
}

function pkgNgeniusCaptured(string $reference, int $minor): void
{
    Http::fake([
        '*identity*' => Http::response(['access_token' => 'ACCESS']),
        '*transactions/outlets/*/orders/*' => Http::response([
            'reference' => $reference, 'amount' => ['value' => $minor, 'currencyCode' => 'AED'], '_embedded' => ['payment' => [['state' => 'CAPTURED']]],
        ]),
    ]);
}

/** @return array<string, mixed> the session a gateway callback of `$type` finds */
function pkgSession(string $type, int $packageId): array
{
    return [
        'payment_type' => $type,
        'payment_data' => [$type === GatewayPaymentService::SELLER_PACKAGE ? 'seller_package_id' : 'customer_package_id' => $packageId],
        'ngenius_order_ref' => 'REF-1',
    ];
}

function pkgNgeniusCallback(string $type): string
{
    return route($type === GatewayPaymentService::SELLER_PACKAGE ? 'ngenius.seller_package_payment_callback' : 'ngenius.customer_package_payment_callback', ['ref' => 'REF-1']);
}

test('N-Genius: a customer package is sent to the gateway at the exact converted price, in minor units', function () {
    pkgCurrencies('AED', 4.0);
    $payer = actingAsStoreUser(pkgPayer(GatewayPaymentService::CUSTOMER_PACKAGE));
    $packageId = pkgPackage(GatewayPaymentService::CUSTOMER_PACKAGE, 12.5);
    pkgNgeniusCreated();

    // The request names no price; 12.50 EUR at 4 AED per euro is 50.00 AED.
    $this->post(route('customer_packages.purchase'), ['customer_package_id' => $packageId, 'payment_option' => 'ngenius', 'amount' => 1])
        ->assertRedirect('https://pay.ngenius.test/page/REF-1');

    $sent = null;
    Http::assertSent(function (ClientRequest $request) use (&$sent) {
        if (str_contains($request->url(), '/transactions/outlets/') && $request->method() === 'POST') {
            $sent = json_decode($request->body(), true);
        }

        return true;
    });

    expect($sent['amount'])->toBe(['currencyCode' => 'AED', 'value' => 5000])
        ->and($sent['merchantAttributes']['redirectUrl'])->toBe(route('ngenius.customer_package_payment_callback'));
    pkgExpectNothing(GatewayPaymentService::CUSTOMER_PACKAGE, $payer);
});

test('N-Genius: the package is activated for the signed-in payer once, after N-Genius reports the converted price captured', function (string $type) {
    pkgCurrencies('AED', 4.0);
    $payer = actingAsStoreUser(pkgPayer($type));
    $bystander = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    pkgNgeniusCaptured('REF-1', 20000);

    pkgExpectNothing($type, $payer);

    foreach ([1, 2] as $attempt) {
        $response = $this->withSession(pkgSession($type, $packageId))->get(pkgNgeniusCallback($type));
        $attempt === 1 ? $response->assertRedirect(route('dashboard')) : $response->assertRedirect(route('home'));
    }

    expect(pkgActivated($type, $payer, $packageId))->toBeTrue()
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->count())->toBe(1);
    pkgExpectNothing($type, $bystander);

    if ($type === GatewayPaymentService::CUSTOMER_PACKAGE) {
        expect((int) $payer->fresh()->remaining_uploads)->toBe(10);
    }
})->with('package types');

test('N-Genius: a capture that is not the converted price activates nothing', function (string $type, int $minor) {
    pkgCurrencies('AED', 4.0);
    $payer = actingAsStoreUser(pkgPayer($type));
    $packageId = pkgPackage($type, 50);
    pkgNgeniusCaptured('REF-1', $minor);

    $this->withSession(pkgSession($type, $packageId))->get(pkgNgeniusCallback($type))->assertRedirect(route('home'));

    pkgExpectNothing($type, $payer);
})->with('package types')->with([
    'the store price unconverted' => [5000],
    'a unit short' => [19900],
    'less than a unit' => [100],
]);

test('N-Genius: an account without a shop that holds a seller package session gets nothing, and the shop owner is not credited', function () {
    pkgCurrencies('AED', 4.0);
    $owner = pkgPayer(GatewayPaymentService::SELLER_PACKAGE);
    $noShop = actingAsStoreUser(createStoreUser('customer'));
    $packageId = pkgPackage(GatewayPaymentService::SELLER_PACKAGE, 50);
    pkgNgeniusCaptured('REF-1', 20000);

    $this->withSession(pkgSession(GatewayPaymentService::SELLER_PACKAGE, $packageId))
        ->get(pkgNgeniusCallback(GatewayPaymentService::SELLER_PACKAGE))->assertRedirect(route('home'));

    pkgExpectNothing(GatewayPaymentService::SELLER_PACKAGE, $owner);
    expect(DB::table('seller_package_payments')->count())->toBe(0)
        ->and(GatewayPayment::query()->where('user_id', $noShop->id)->count())->toBe(0);
});

test('N-Genius: a callback of another payment type, or for an order this session did not create, activates nothing', function () {
    pkgCurrencies('AED', 4.0);
    $type = GatewayPaymentService::SELLER_PACKAGE;
    $payer = actingAsStoreUser(pkgPayer($type));
    $packageId = pkgPackage($type, 50);
    pkgNgeniusCaptured('REF-ELSEWHERE', 20000);

    $this->withSession(pkgSession($type, $packageId))->get(route('ngenius.customer_package_payment_callback', ['ref' => 'REF-1']));
    $this->withSession(pkgSession($type, $packageId))->get(route('ngenius.seller_package_payment_callback', ['ref' => 'REF-ELSEWHERE']));

    pkgExpectNothing($type, $payer);
});

// --- mobile API: iyzico --------------------------------------------------------------

/** iyzico reports each of `$paidPrices` in turn (TRY), the last one for every later call. */
function pkgFakeIyzico(float ...$paidPrices): void
{
    test()->mock(IyzicoPayments::class)
        ->shouldReceive('retrieve')
        ->andReturn(...array_map(fn (float $paid) => ['paid' => true, 'paid_price' => $paid, 'currency' => 'TRY', 'basket_id' => 'BASKET-1', 'raw' => '{}'], $paidPrices));
}

test('API iyzico: a package is priced from the database, converted to the gateway currency, and paid once', function (string $type) {
    pkgCurrencies('TRY', 30.0);
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    $request = ['payment_type' => $type, 'package_id' => $packageId, 'amount' => 1, 'user_id' => 999, 'token' => 'T-1'];
    $post = fn (array $body) => $this->withToken(storeApiToken($payer))->postJson('/api/v1/store/iyzico/success', $body);

    // 50 EUR at 30 TRY per euro is 1500.00 TRY: the unconverted price and a lira short are refused.
    pkgFakeIyzico(50.0, 1499.0, 1500.0);
    $post($request)->assertJsonPath('result', false);
    $post([...$request, 'token' => 'T-2'])->assertJsonPath('result', false);
    pkgExpectNothing($type, $payer);

    $post([...$request, 'token' => 'T-3'])->assertJsonPath('result', true);
    $post([...$request, 'token' => 'T-3'])->assertJsonPath('result', false);

    expect(pkgActivated($type, $payer, $packageId))->toBeTrue()
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->count())->toBe(1)
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->value('amount'))->toBe('50.00');
})->with('package types');

test('API iyzico: the request\'s account is ignored, and an account without a shop gets no seller package', function () {
    pkgCurrencies('TRY', 30.0);
    $owner = pkgPayer(GatewayPaymentService::SELLER_PACKAGE);
    $noShop = createStoreUser('customer');
    $packageId = pkgPackage(GatewayPaymentService::SELLER_PACKAGE, 50);
    pkgFakeIyzico(1500.0);

    $this->withToken(storeApiToken($noShop))
        ->postJson('/api/v1/store/iyzico/success', ['payment_type' => GatewayPaymentService::SELLER_PACKAGE, 'package_id' => $packageId, 'user_id' => $owner->id, 'token' => 'T-1'])
        ->assertJsonPath('result', false);

    pkgExpectNothing(GatewayPaymentService::SELLER_PACKAGE, $owner);
    expect(GatewayPayment::query()->where('user_id', $noShop->id)->count())->toBe(0);
});

// --- mobile API: SSLCommerz ---------------------------------------------------------------

/** A success post as SSLCommerz signs it (see LegacyGatewayPaymentCreditingTest::sslcommerzPost). */
function pkgSslcommerzPost(string $tranId, string $amount): array
{
    $post = ['tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount, 'status' => 'VALID'];
    $post['verify_key'] = implode(',', array_keys($post));

    $signed = array_intersect_key($post, array_flip(explode(',', $post['verify_key'])));
    $signed['store_passwd'] = md5('store-pass');
    ksort($signed);
    $post['verify_sign'] = md5(implode('&', array_map(fn ($key, $value) => "$key=$value", array_keys($signed), $signed)));

    return $post;
}

/** SSLCommerz's validation server confirms each of `$amounts` (BDT) in turn; Http::fake keeps the first stub, so one sequence. */
function pkgSslcommerzValidates(string $tranId, string ...$amounts): void
{
    $sequence = Http::sequence();

    foreach ($amounts as $amount) {
        $sequence->push(['status' => 'VALID', 'tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount, 'currency_type' => 'BDT', 'currency_amount' => $amount]);
    }

    Http::fake(['*sslcommerz.com/validator/*' => $sequence]);
}

test('API SSLCommerz: a package pays what begin() recorded, once, only when the converted price is confirmed', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $payer->id, $type, null, $packageId, 50);

    // 50 EUR at 100 BDT per euro is 5000.00 BDT; SSLCommerz first confirms 4000.00, then 5000.00.
    pkgSslcommerzValidates('AIZ-1', '4000.00', '5000.00');
    $this->post('/api/v1/store/sslcommerz/success', pkgSslcommerzPost('AIZ-1', '4000.00'))->assertJsonPath('result', false);
    pkgExpectNothing($type, $payer);
    expect(GatewayPayment::query()->where('reference', 'AIZ-1')->value('completed_at'))->toBeNull();

    $this->post('/api/v1/store/sslcommerz/success', pkgSslcommerzPost('AIZ-1', '5000.00'))->assertJsonPath('result', true);
    $this->post('/api/v1/store/sslcommerz/success', pkgSslcommerzPost('AIZ-1', '5000.00'))->assertJsonPath('result', false);

    expect(pkgActivated($type, $payer, $packageId))->toBeTrue()
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->count())->toBe(1);
})->with('package types');

test('API SSLCommerz: a transaction begin() did not record activates no package, whoever it names', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $victim = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    pkgSslcommerzValidates('AIZ-FORGED', '5000.00');

    $this->post('/api/v1/store/sslcommerz/success', pkgSslcommerzPost('AIZ-FORGED', '5000.00') + ['value_a' => (string) $victim->id, 'value_b' => (string) $packageId, 'value_c' => $type, 'value_d' => '50'])
        ->assertJsonPath('result', false);

    pkgExpectNothing($type, $victim);
})->with('package types');

// --- mobile API: Nagad ------------------------------------------------------------------------

/** Nagad reports each of `$amounts` (BDT) in turn, for the order and payment reference given; one sequence, as Http::fake keeps the first stub. */
function pkgNagadReports(string $orderId, string $paymentRefId, string ...$amounts): void
{
    $sequence = Http::sequence();

    foreach ($amounts as $amount) {
        $sequence->push(['status' => 'Success', 'orderId' => $orderId, 'paymentRefId' => $paymentRefId, 'amount' => $amount]);
    }

    Http::fake(['*api/dfs/verify/payment/*' => $sequence]);
}

test('API Nagad: a package pays what begin() recorded, once, only when the converted price is reported', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-1', $payer->id, $type, null, $packageId, 50);
    $verify = fn () => $this->getJson('/api/v1/store/nagad/verify/'.$type.'?payment_ref_id=NREF-1');

    // 50 EUR at 100 BDT per euro is 5000 BDT: Nagad first reports the unconverted price, then the right one twice.
    pkgNagadReports('Inv-1', 'NREF-1', '50', '5000', '5000');
    $verify()->assertJsonPath('result', false);
    pkgExpectNothing($type, $payer);

    $verify()->assertJsonPath('result', true);
    $verify()->assertJsonPath('result', false);

    expect(pkgActivated($type, $payer, $packageId))->toBeTrue()
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->count())->toBe(1);
})->with('package types');

test('API Nagad: a payment for an order begin() did not record activates no package', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    app(GatewayPaymentService::class)->begin('nagad', 'Inv-1', $payer->id, $type, null, $packageId, 50);
    pkgNagadReports('Inv-UNKNOWN', 'NREF-1', '5000');

    $this->getJson('/api/v1/store/nagad/verify/'.$type.'?payment_ref_id=NREF-1')->assertJsonPath('result', false);

    pkgExpectNothing($type, $payer);
    expect(GatewayPayment::query()->where('reference', 'Inv-1')->value('completed_at'))->toBeNull();
})->with('package types');

// --- mobile API: bKash --------------------------------------------------------------------------

/** bKash grants a token and reports the payment completed for `$amount` (BDT), whichever call asks. */
function pkgBkashCompleted(string $amount): void
{
    $result = ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => $amount, 'currency' => 'BDT'];

    Http::fake([
        '*bka.sh/*checkout/token/grant' => Http::response(['id_token' => 'grant-token']),
        '*bka.sh/*checkout/execute' => Http::response($result),
        '*bka.sh/*checkout/payment/status' => Http::response($result),
    ]);
}

test('API bKash: a package paid for the unconverted price is refused', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $payer = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    pkgBkashCompleted('50');

    $this->withToken(storeApiToken($payer))
        ->postJson('/api/v1/store/bkash/api/success', ['payment_type' => $type, 'package_id' => $packageId, 'payment_id' => 'PAY-1', 'amount' => 1])
        ->assertJsonPath('result', false);

    pkgExpectNothing($type, $payer);
})->with('package types');

test('API bKash: a package is activated for the signed-in payer once, when bKash reports the converted price completed', function (string $type) {
    pkgCurrencies('BDT', 100.0);
    $payer = pkgPayer($type);
    $bystander = pkgPayer($type);
    $packageId = pkgPackage($type, 50);
    pkgBkashCompleted('5000');
    $request = ['payment_type' => $type, 'package_id' => $packageId, 'payment_id' => 'PAY-1', 'user_id' => $bystander->id];

    $this->withToken(storeApiToken($payer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', true);
    $this->withToken(storeApiToken($payer))->postJson('/api/v1/store/bkash/api/success', $request)->assertJsonPath('result', false);

    expect(pkgActivated($type, $payer, $packageId))->toBeTrue()
        ->and(GatewayPayment::query()->where('user_id', $payer->id)->count())->toBe(1);
    pkgExpectNothing($type, $bystander);
})->with('package types');
