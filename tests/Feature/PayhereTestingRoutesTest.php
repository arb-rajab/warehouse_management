<?php

use Illuminate\Support\Facades\DB;

/**
 * PayHere's three `/temp/payhere/{checkout,wallet,customer_package}/testing`
 * routes print a signed payment form for a made-up buyer. They answer only
 * while PayHere is enabled, its effective mode is sandbox
 * (`payhere_sandbox`) and the app is not in production: otherwise a 404
 * (EnsurePaymentGatewaySandbox).
 */
beforeEach(function () {
    $this->withoutVite();
    config([
        'store.enabled_payment_gateways' => ['payhere'],
        'services.payhere.merchant_id' => 'MERCHANT-1',
        'services.payhere.secret' => 'real-secret',
    ]);
    actingAsStoreUser(createStoreUser('customer'));
});

function payhereSandbox(?string $value): void
{
    DB::table('business_settings')->where('type', 'payhere_sandbox')->delete();

    if ($value !== null) {
        DB::table('business_settings')->insert(['type' => 'payhere_sandbox', 'value' => $value]);
    }
}

dataset('payhere testing routes', [
    'checkout' => '/temp/payhere/checkout/testing',
    'wallet' => '/temp/payhere/wallet/testing',
    'customer package' => '/temp/payhere/customer_package/testing',
]);

test('a PayHere testing route answers while PayHere is enabled in sandbox mode outside production', function (string $uri) {
    payhereSandbox('1');

    $this->get($uri)->assertOk();
})->with('payhere testing routes');

test('a PayHere testing route is a 404 in live mode', function (string $uri) {
    payhereSandbox('0');

    $this->get($uri)->assertNotFound();
})->with('payhere testing routes');

test('a PayHere testing route is a 404 while no sandbox mode is saved, as the mode defaults to live', function (string $uri) {
    payhereSandbox(null);

    $this->get($uri)->assertNotFound();
})->with('payhere testing routes');

test('a PayHere testing route is a 404 in production even in sandbox mode', function (string $uri) {
    payhereSandbox('1');
    $this->app['env'] = 'production';

    $this->get($uri)->assertNotFound();
})->with('payhere testing routes');

test('a PayHere testing route is a 404 while PayHere is not enabled, even in sandbox mode', function (string $uri) {
    payhereSandbox('1');
    config(['store.enabled_payment_gateways' => ['bkash']]);

    $this->get($uri)->assertNotFound();
})->with('payhere testing routes');

test('the PayHere routes that are not testing routes keep answering in live mode and in production', function () {
    payhereSandbox('0');
    $this->app['env'] = 'production';

    expect($this->post(route('payhere.checkout.cancel'))->getStatusCode())->not->toBe(404);
});
