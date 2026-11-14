<?php

use App\Models\CombinedOrder;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wallet;
use App\Services\GatewayPaymentService;
use App\Services\GatewayReturn;
use App\Services\IyzicoPayments;
use App\Services\VerifiedPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * SSLCommerz, AamarPay and iyzico send the customer back with a cross-site POST.
 * With SESSION_SAME_SITE=lax that POST carries no session cookie, so the old
 * callbacks found no pending payment in the session and credited nothing (and
 * the fresh session they answered with signed the customer out). The payment
 * is now recorded at the start under the reference sent to the gateway; the
 * return gives only that reference, the gateway's server must confirm it, the
 * confirmed amount and currency must be the recorded ones to the cent, and the
 * recorded payer is credited once (App\Services\GatewayReturn). Every request
 * below arrives with no session and nobody signed in, unless a test says so.
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['aamarpay', 'iyzico', 'sslcommerz'],
        'services.sslcommerz.store_id' => 'store-1',
        'services.sslcommerz.store_password' => 'store-pass',
        'services.aamarpay.store_id' => 'store-1',
        'services.aamarpay.signature_key' => 'sig-1',
    ]);
    Http::preventStrayRequests();
});

dataset('cross-site returns', ['SSLCommerz' => 'sslcommerz', 'AamarPay' => 'aamarpay', 'iyzico' => 'iyzico']);

/** A reference as the gateway's pay() makes it: ours for SSLCommerz and AamarPay, iyzico's token for iyzico. */
function returnReference(string $gateway, string $suffix = '1'): string
{
    return $gateway === 'iyzico' ? 'IYZ-TOKEN-'.$suffix : 'WEB-RETURN-'.$suffix;
}

function returnOrder(StoreUser $customer, float $total = 30): CombinedOrder
{
    $combined = CombinedOrder::create(['user_id' => $customer->id, 'shipping_address' => '{}', 'grand_total' => $total]);
    createStoreOrder($customer, ['combined_order_id' => $combined->id, 'grand_total' => $total]);

    return $combined;
}

/** @return list<string> */
function returnOrderStatuses(CombinedOrder $combined): array
{
    return Order::query()->where('combined_order_id', $combined->id)->pluck('payment_status')->unique()->values()->all();
}

/** A pending cart payment of 30 (store currency) charged as 3915.00 BDT. */
function pendingCartReturn(string $gateway, StoreUser $payer, CombinedOrder $combined, string $suffix = '1'): GatewayPayment
{
    return pendingGatewayReturn($gateway, returnReference($gateway, $suffix), $payer, 'cart_payment', 30, 3915.0, 'BDT', $combined->id);
}

/**
 * The gateway's own server reports the payment behind `$reference`, once per
 * report given, in turn (the last one for every later call). `$during` runs
 * inside the first report, while the return is between verifying and crediting.
 *
 * @param  list<array{amount: string, currency?: string, paid?: bool}>  $reports
 */
function gatewayServerReports(string $gateway, string $reference, array $reports, ?Closure $during = null): void
{
    $calls = 0;
    $next = function () use (&$calls, $reports, $during): array {
        if ($calls === 0 && $during !== null) {
            $during();
        }

        return $reports[min($calls++, count($reports) - 1)] + ['currency' => 'BDT', 'paid' => true];
    };

    if ($gateway === 'iyzico') {
        test()->mock(IyzicoPayments::class)->shouldReceive('retrieve')->with($reference)->andReturnUsing(function () use ($next): array {
            $report = $next();

            return ['paid' => $report['paid'], 'paid_price' => (float) $report['amount'], 'currency' => $report['currency'], 'basket_id' => 'BASKET', 'raw' => '{}'];
        });

        return;
    }

    $pattern = $gateway === 'sslcommerz' ? '*sslcommerz.com/validator/*' : '*aamarpay.com/api/v1/trxcheck/*';

    Http::fake([$pattern => function () use ($gateway, $reference, $next) {
        $report = $next();

        return Http::response($gateway === 'sslcommerz'
            ? ['status' => $report['paid'] ? 'VALID' : 'INVALID_TRANSACTION', 'tran_id' => $reference, 'val_id' => 'VAL-'.$reference,
                'amount' => $report['amount'], 'currency_type' => $report['currency'], 'currency_amount' => $report['amount']]
            : ['pay_status' => $report['paid'] ? 'Successful' : 'Failed', 'mer_txnid' => $reference, 'amount' => $report['amount'], 'currency' => $report['currency']]);
    }]);
}

/** The gateway's server is never asked: the return was refused before any outbound call. */
function gatewayServerNotAsked(string $gateway): void
{
    if ($gateway === 'iyzico') {
        test()->mock(IyzicoPayments::class)->shouldNotReceive('retrieve');
    }
}

/** A success post as SSLCommerz signs it (md5 over the `verify_key` fields and the store password's md5). */
function signedSslcommerzReturn(string $tranId, string $amount): array
{
    $post = ['tran_id' => $tranId, 'val_id' => 'VAL-'.$tranId, 'amount' => $amount, 'status' => 'VALID', 'value_a' => $tranId];
    $post['verify_key'] = implode(',', array_keys($post));
    $signed = array_intersect_key($post, array_flip(explode(',', $post['verify_key'])));
    $signed['store_passwd'] = md5('store-pass');
    ksort($signed);
    $post['verify_sign'] = md5(implode('&', array_map(fn ($key, $value) => "$key=$value", array_keys($signed), $signed)));

    return $post;
}

/** The gateway's success return for `$reference`, as its customer's browser posts it. */
function postGatewayReturn(mixed $test, string $gateway, string $reference, string $amount = '3915.00'): TestResponse
{
    return match ($gateway) {
        'sslcommerz' => $test->post('/temp/sslcommerz/success', signedSslcommerzReturn($reference, $amount)),
        'aamarpay' => $test->post('/temp/aamarpay/success', ['mer_txnid' => $reference, 'pay_status' => 'Successful', 'amount' => $amount, 'opt_a' => 'wallet_payment']),
        'iyzico' => $test->post('/temp/iyzico/payment/callback/cart_payment', ['token' => $reference]),
    };
}

function expectRefusedReturn(TestResponse $response): void
{
    $response->assertStatus(303)->assertRedirect(route('gateway_payment.outcome', ['outcome' => 'unverified']));
}

// --- crediting ---------------------------------------------------------------------

test('a return with no session credits the recorded owner once, and sends the browser on with a 303', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);
    gatewayServerReports($gateway, $pending->reference, [['amount' => '3915.00']]);

    $response = postGatewayReturn($this, $gateway, $pending->reference);

    $response->assertStatus(303)->assertRedirectContains('/temp/payment/finished/'.$pending->id);
    expect(returnOrderStatuses($combined))->toBe(['paid'])
        ->and($pending->fresh()->completed_at)->not->toBeNull()
        ->and(GatewayPayment::count())->toBe(1)
        ->and(Order::query()->where('combined_order_id', $combined->id)->where('commission_calculated', 1)->count())->toBe(1)
        ->and($response->headers->getCookies())->toBe([]);
    $this->assertGuest('store');
})->with('cross-site returns');

test('a replayed return credits nothing more, and lands on the same page', function (string $gateway) {
    $owner = createStoreUser('customer');
    $owner->forceFill(['balance' => 0])->save();
    $pending = pendingGatewayReturn($gateway, returnReference($gateway), $owner, 'wallet_payment', 25, 3262.5);
    gatewayServerReports($gateway, $pending->reference, [['amount' => '3262.50']]);

    $first = postGatewayReturn($this, $gateway, $pending->reference, '3262.50');
    $second = postGatewayReturn($this, $gateway, $pending->reference, '3262.50');

    expect((float) $owner->fresh()->balance)->toBe(25.0)
        ->and(Wallet::query()->where('user_id', $owner->id)->count())->toBe(1);
    $first->assertRedirectContains('/temp/payment/finished/'.$pending->id);
    $second->assertStatus(303)->assertRedirectContains('/temp/payment/finished/'.$pending->id);
})->with('cross-site returns');

test('the owner, the order and the amount come from the record, never from the return', function () {
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $combined = returnOrder($owner);
    $strangersOrder = returnOrder($stranger);
    $pending = pendingCartReturn('sslcommerz', $owner, $combined);
    gatewayServerReports('sslcommerz', $pending->reference, [['amount' => '3915.00']]);

    $this->post('/temp/sslcommerz/success', signedSslcommerzReturn($pending->reference, '3915.00') + [
        'value_b' => (string) $strangersOrder->id, 'value_c' => 'wallet_payment', 'value_d' => (string) $stranger->id, 'user_id' => $stranger->id,
    ]);

    expect(returnOrderStatuses($combined))->toBe(['paid'])
        ->and(returnOrderStatuses($strangersOrder))->toBe(['unpaid'])
        ->and(Wallet::query()->where('user_id', $stranger->id)->count())->toBe(0);
});

// --- refusals ----------------------------------------------------------------------

test('a forged or unknown reference is refused without asking the gateway', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    pendingCartReturn($gateway, $owner, $combined);
    gatewayServerNotAsked($gateway);

    expectRefusedReturn(postGatewayReturn($this, $gateway, returnReference($gateway, 'FORGED')));
    expectRefusedReturn(postGatewayReturn($this, $gateway, ''));

    expect(returnOrderStatuses($combined))->toBe(['unpaid']);
    Http::assertNothingSent();
})->with('cross-site returns');

test('a reference recorded for another gateway is refused', function (string $gateway) {
    $other = $gateway === 'sslcommerz' ? 'aamarpay' : 'sslcommerz';
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingGatewayReturn($other, returnReference($gateway), $owner, 'cart_payment', 30, 3915.0, 'BDT', $combined->id);
    gatewayServerNotAsked($gateway);

    expectRefusedReturn(postGatewayReturn($this, $gateway, $pending->reference));

    expect(returnOrderStatuses($combined))->toBe(['unpaid'])
        ->and($pending->fresh()->completed_at)->toBeNull();
    Http::assertNothingSent();
})->with('cross-site returns');

test('a pending payment the mobile app started is not completed by a storefront return', function () {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-1', $owner->id, 'cart_payment', $combined->id, null, 30);

    expectRefusedReturn(postGatewayReturn($this, 'sslcommerz', 'AIZ-1'));

    expect(returnOrderStatuses($combined))->toBe(['unpaid']);
    Http::assertNothingSent();
});

test('an expired reference is refused, and stays pending for reconciling', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);
    gatewayServerNotAsked($gateway);

    $this->travel(181)->minutes();

    expectRefusedReturn(postGatewayReturn($this, $gateway, $pending->reference));

    expect(returnOrderStatuses($combined))->toBe(['unpaid'])
        ->and($pending->fresh()->completed_at)->toBeNull();
    Http::assertNothingSent();
})->with('cross-site returns');

test('the lifetime of a reference is config(store.gateway_return_ttl_minutes)', function () {
    config(['store.gateway_return_ttl_minutes' => 30]);
    $owner = createStoreUser('customer');

    $pending = pendingCartReturn('aamarpay', $owner, returnOrder($owner));

    expect($pending->expires_at->diffInMinutes(now(), true))->toBeGreaterThan(29.0)->toBeLessThan(31.0);
});

test('an amount or currency that is not the recorded one to the cent is refused without using up the reference', function (string $gateway, string $amount, string $currency) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);
    gatewayServerReports($gateway, $pending->reference, [['amount' => $amount, 'currency' => $currency], ['amount' => '3915.00']]);

    expectRefusedReturn(postGatewayReturn($this, $gateway, $pending->reference, $amount));

    expect(returnOrderStatuses($combined))->toBe(['unpaid'])
        ->and($pending->fresh()->completed_at)->toBeNull();

    // The same reference, now confirmed for the recorded amount, still credits.
    postGatewayReturn($this, $gateway, $pending->reference)->assertRedirectContains('/temp/payment/finished/'.$pending->id);

    expect(returnOrderStatuses($combined))->toBe(['paid']);
})->with('cross-site returns')->with([
    'a cent short' => ['3914.99', 'BDT'],
    'a cent over' => ['3915.01', 'BDT'],
    'the store-currency figure' => ['30.00', 'BDT'],
    'the right figure in another currency' => ['3915.00', 'USD'],
]);

test('a payment the gateway does not confirm is refused and stays pending', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);
    gatewayServerReports($gateway, $pending->reference, [['amount' => '3915.00', 'paid' => false]]);

    expectRefusedReturn(postGatewayReturn($this, $gateway, $pending->reference));

    expect(returnOrderStatuses($combined))->toBe(['unpaid'])
        ->and($pending->fresh()->completed_at)->toBeNull();
})->with('cross-site returns');

test('an SSLCommerz post with a broken signature is refused', function () {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn('sslcommerz', $owner, $combined);

    $this->post('/temp/sslcommerz/success', ['verify_sign' => 'FORGED'] + signedSslcommerzReturn($pending->reference, '3915.00'))
        ->assertRedirect(route('gateway_payment.outcome', ['outcome' => 'unverified']));

    expect(returnOrderStatuses($combined))->toBe(['unpaid']);
    Http::assertNothingSent();
});

// --- the double-credit race --------------------------------------------------------

test('two returns that both pass verification credit once', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);

    // SQLite runs one transaction at a time, so the race is staged: while this return
    // is between the gateway's confirmation and its credit, the other one (which was
    // confirmed too) claims the row first.
    gatewayServerReports($gateway, $pending->reference, [['amount' => '3915.00']], during: function () use ($pending): void {
        expect(app(GatewayPaymentService::class)->completePending(new VerifiedPayment($pending->gateway, $pending->reference, 30)))->toBeTrue();
    });

    postGatewayReturn($this, $gateway, $pending->reference)
        ->assertStatus(303)
        ->assertRedirectContains('/temp/payment/finished/'.$pending->id);

    expect(returnOrderStatuses($combined))->toBe(['paid'])
        ->and(Order::query()->where('combined_order_id', $combined->id)->where('commission_calculated', 1)->count())->toBe(1)
        ->and(GatewayPayment::count())->toBe(1);
})->with('cross-site returns');

test('the credit claims the row under a lock that re-checks it is still pending and unexpired', function () {
    $owner = createStoreUser('customer');
    $pending = pendingGatewayReturn('aamarpay', 'WEB-LOCK-1', $owner, 'wallet_payment', 25, 25);
    $service = app(GatewayPaymentService::class);

    $this->travel(181)->minutes();
    expect($service->completePending(new VerifiedPayment('aamarpay', 'WEB-LOCK-1', 25)))->toBeFalse();

    $this->travelBack();
    expect($service->completePending(new VerifiedPayment('aamarpay', 'WEB-LOCK-1', 25)))->toBeTrue()
        ->and($service->completePending(new VerifiedPayment('aamarpay', 'WEB-LOCK-1', 25)))->toBeFalse()
        ->and($service->completePending(new VerifiedPayment('sslcommerz', 'WEB-LOCK-1', 25)))->toBeFalse()
        ->and((float) $owner->fresh()->balance)->toBe(25.0)
        ->and($pending->fresh()->completed_at)->not->toBeNull();
});

// --- where the browser lands -------------------------------------------------------

test('the payer, signed in through a same-site session, lands on their order confirmation', function (string $gateway) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner);
    $pending = pendingCartReturn($gateway, $owner, $combined);
    gatewayServerReports($gateway, $pending->reference, [['amount' => '3915.00']]);
    actingAsStoreUser($owner);

    $location = postGatewayReturn($this, $gateway, $pending->reference)->assertStatus(303)->headers->get('Location');

    $this->get($location)->assertRedirect(route('order_confirmed'));
    expect(session('combined_order_id'))->toBe($combined->id);
})->with('cross-site returns');

test('the payer lands on their wallet after a top-up, and on the dashboard after a package', function (string $type, string $route, string $message) {
    $owner = createStoreUser('customer');
    $pending = pendingGatewayReturn('aamarpay', 'WEB-LAND-1', $owner, $type, 25, 25, 'BDT', null, $type === 'wallet_payment' ? null : 1);
    $pending->forceFill(['completed_at' => now()])->save();
    actingAsStoreUser($owner);

    $this->withSession(['payment_type' => $type, 'payment_data' => ['amount' => 25]])
        ->get(URL::temporarySignedRoute(GatewayReturn::FINISHED_ROUTE, now()->addMinutes(30), ['payment' => $pending->id]))
        ->assertRedirect(route($route));

    expect(flashedMessage())->toBe(__($message))
        ->and(session('payment_type'))->toBeNull()
        ->and(session('payment_data'))->toBeNull();
})->with([
    'a wallet top-up' => ['wallet_payment', 'wallet.index', 'storefront.checkout.recharge_completed'],
    'a customer package' => ['customer_package_payment', 'dashboard', 'storefront.common.package_purchasing_successful'],
]);

test('a browser that is not signed in as the payer gets the neutral page, which names nothing about the payment', function (?string $who) {
    $owner = createStoreUser('customer');
    $combined = returnOrder($owner, 1234.56);
    $pending = pendingGatewayReturn('aamarpay', 'WEB-LAND-1', $owner, 'cart_payment', 1234.56, 1234.56, 'BDT', $combined->id);
    $pending->forceFill(['completed_at' => now()])->save();

    if ($who === 'someone else') {
        actingAsStoreUser(createStoreUser('customer'));
    }

    $page = $this->get(URL::temporarySignedRoute(GatewayReturn::FINISHED_ROUTE, now()->addMinutes(30), ['payment' => $pending->id]));

    $page->assertOk()
        ->assertSee(__('storefront.checkout.payment_received'))
        ->assertSee(__('storefront.checkout.payment_received_sign_in'))
        ->assertSee(route('shop.login'), false)
        ->assertDontSee('1234.56')
        ->assertDontSee($owner->email)
        ->assertDontSee('WEB-LAND-1');
    expect(session('combined_order_id'))->toBeNull()
        ->and(session('url.intended'))->toContain('/temp/payment/finished/'.$pending->id);
})->with(['nobody signed in' => null, 'another customer signed in' => 'someone else']);

test('the neutral page is translated', function () {
    expect(trans('storefront.checkout.payment_received', [], 'ar'))->not->toBe('storefront.checkout.payment_received')
        ->and(trans('storefront.checkout.payment_received_sign_in', [], 'ar'))->not->toBe('storefront.checkout.payment_received_sign_in')
        ->and(trans('storefront.checkout.payment_received_back_to_shop', [], 'ar'))->not->toBe('storefront.checkout.payment_received_back_to_shop');
});

test('the link to a finished payment must be signed and is valid for 30 minutes', function () {
    $owner = createStoreUser('customer');
    $pending = pendingGatewayReturn('aamarpay', 'WEB-LAND-1', $owner, 'wallet_payment', 25, 25);
    $pending->forceFill(['completed_at' => now()])->save();
    actingAsStoreUser($owner);
    $signed = app(GatewayReturn::class)->finished($pending)->getTargetUrl();

    $this->get('/temp/payment/finished/'.$pending->id)->assertForbidden();
    $this->get($signed.'x')->assertForbidden();

    $this->travel(31)->minutes();
    $this->get($signed)->assertForbidden();
});

test('a failed or cancelled return sends the browser to a page that says so', function (string $uri, string $outcome, string $message, string $landing) {
    $response = $this->post($uri, ['tran_id' => 'WEB-ANY']);

    $response->assertStatus(303)->assertRedirect(route('gateway_payment.outcome', ['outcome' => $outcome]));

    $this->withSession(['payment_data' => ['amount' => 25], 'order_id' => 7])
        ->get(route('gateway_payment.outcome', ['outcome' => $outcome]))
        ->assertRedirect(route($landing));
    expect(flashedMessage())->toBe(__($message))
        ->and(session('payment_data'))->toBeNull();
})->with([
    'SSLCommerz fail' => ['/temp/sslcommerz/fail', 'failed', 'storefront.checkout.payment_failed', 'cart'],
    'SSLCommerz cancel' => ['/temp/sslcommerz/cancel', 'cancelled', 'storefront.checkout.payment_cancelled', 'home'],
    'AamarPay fail' => ['/temp/aamarpay/fail', 'failed', 'storefront.checkout.payment_failed', 'cart'],
]);

test('a refused return lands on "could not be verified", signed in or not', function () {
    $this->get(route('gateway_payment.outcome', ['outcome' => 'unverified']))->assertRedirect(route('home'));

    expect(flashedMessage())->toBe(__('storefront.checkout.payment_could_not_be_verified'));
});

test('an outcome page that is not one of the three is not found', function () {
    $this->get('/temp/payment/outcome/paid')->assertNotFound();
});

// --- throttling and logging --------------------------------------------------------

test('one reference is returned at most 10 times a minute', function () {
    foreach (range(1, 10) as $attempt) {
        postGatewayReturn($this, 'aamarpay', 'WEB-FLOOD-1')->assertStatus(303);
    }

    postGatewayReturn($this, 'aamarpay', 'WEB-FLOOD-1')->assertTooManyRequests();
    postGatewayReturn($this, 'aamarpay', 'WEB-FLOOD-2')->assertStatus(303);

    $this->travel(61)->seconds();
    postGatewayReturn($this, 'aamarpay', 'WEB-FLOOD-1')->assertStatus(303);
});

test('one address makes at most 30 returns a minute', function () {
    foreach (range(1, 30) as $attempt) {
        postGatewayReturn($this, 'aamarpay', 'WEB-SPRAY-'.$attempt)->assertStatus(303);
    }

    postGatewayReturn($this, 'aamarpay', 'WEB-SPRAY-31')->assertTooManyRequests();
});

test('a refusal is logged with its reason and a shortened reference, never the post', function () {
    Log::spy();
    $owner = createStoreUser('customer');
    $pending = pendingCartReturn('sslcommerz', $owner, returnOrder($owner));
    $this->travel(181)->minutes();

    postGatewayReturn($this, 'sslcommerz', $pending->reference);
    postGatewayReturn($this, 'sslcommerz', 'WEB-NEVER-STARTED-1');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['reason'] === 'expired'
        && $context['reference'] === 'WEB-RE…'
        && ! str_contains(json_encode($context), $pending->reference) && ! str_contains(json_encode($context), 'verify_sign'))->once();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['reason'] === 'unknown'
        && $context['reference'] === 'WEB-NE…' && ! str_contains(json_encode($context), 'WEB-NEVER-STARTED-1'))->once();
});

// --- cleanup -----------------------------------------------------------------------

test('the daily prune removes only pending payments that expired over 90 days ago', function () {
    $owner = createStoreUser('customer');
    $old = pendingGatewayReturn('aamarpay', 'WEB-OLD', $owner, 'wallet_payment', 25, 25);
    $recent = pendingGatewayReturn('aamarpay', 'WEB-RECENT', $owner, 'wallet_payment', 25, 25);
    $completed = pendingGatewayReturn('aamarpay', 'WEB-DONE', $owner, 'wallet_payment', 25, 25);
    $api = app(GatewayPaymentService::class)->begin('sslcommerz', 'AIZ-OLD', $owner->id, 'wallet_payment', null, null, 25);
    $old->forceFill(['expires_at' => now()->subDays(91)])->save();
    $recent->forceFill(['expires_at' => now()->subDays(89)])->save();
    $completed->forceFill(['expires_at' => now()->subDays(400), 'completed_at' => now()->subDays(400)])->save();
    $api->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->artisan('model:prune', ['--model' => [GatewayPayment::class]])->assertSuccessful();

    expect(GatewayPayment::query()->pluck('reference')->sort()->values()->all())->toBe(['AIZ-OLD', 'WEB-DONE', 'WEB-RECENT']);
});
