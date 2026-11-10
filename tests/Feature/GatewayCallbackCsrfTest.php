<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Payment gateways and Sign in with Apple POST to the store from outside, with
 * no CSRF token, so their callbacks are exempt (bootstrap/app.php); every other
 * POST of the store keeps the check. Laravel skips the check while the
 * environment is `testing`, so these tests run as `local` to make it real.
 */
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
    $this->app['env'] = 'local';
    config(['store.enabled_payment_gateways' => ['sslcommerz', 'payhere', 'iyzico', 'aamarpay', 'bkash']]);
});

test('the CSRF check is real in these tests: an ordinary POST without a token is refused', function () {
    $this->post('/temp/cart/addtocart', ['id' => 1, 'quantity' => 1])->assertStatus(419);
});

dataset('gateway callbacks that are posted to the store', [
    'SSLCommerz success' => '/temp/sslcommerz/success',
    'SSLCommerz fail' => '/temp/sslcommerz/fail',
    'SSLCommerz cancel' => '/temp/sslcommerz/cancel',
    'AamarPay success' => '/temp/aamarpay/success',
    'AamarPay fail' => '/temp/aamarpay/fail',
    'PayHere checkout notify' => '/temp/payhere/checkout/notify',
    'PayHere checkout return' => '/temp/payhere/checkout/return',
    'PayHere wallet notify' => '/temp/payhere/wallet/notify',
    'PayHere package notify' => '/temp/payhere/customer_package_payment/notify',
    'Iyzico callback' => '/temp/iyzico/payment/callback/cart_payment/25/iyzico/1',
    'Sign in with Apple' => '/temp/apple-callback',
]);

test('a gateway callback posted without a CSRF token is not refused as an expired page', function (string $uri) {
    expect($this->post($uri, ['tran_id' => '1', 'order_id' => '1', 'status_code' => '2', 'md5sig' => 'FORGED'])->getStatusCode())->not->toBe(419);
})->with('gateway callbacks that are posted to the store');

test('a forged callback is accepted past CSRF but still completes nothing', function (string $uri) {
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);
    $noise = createStoreOrder(createStoreUser('customer'));

    $this->post($uri, [
        'tran_id' => (string) $order->id, 'mer_txnid' => (string) $order->id, 'order_id' => (string) $order->id,
        'val_id' => 'FORGED', 'pay_status' => 'Successful', 'status_code' => '2', 'md5sig' => 'FORGED', 'amount' => '5.00',
    ]);

    expect($order->fresh()->payment_status)->not->toBe('paid')
        ->and($noise->fresh()->payment_status)->not->toBe('paid');
})->with([
    'SSLCommerz' => '/temp/sslcommerz/success',
    'AamarPay' => '/temp/aamarpay/success',
    'PayHere' => '/temp/payhere/checkout/notify',
    'Iyzico' => '/temp/iyzico/payment/callback/cart_payment/5/iyzico/1',
]);

dataset('posts that keep the CSRF check', [
    'storefront login' => '/temp/login',
    'cart' => '/temp/cart/addtocart',
    'checkout payment' => '/temp/checkout/payment',
    'subscribe' => '/temp/subscribers',
]);

test('every other POST keeps the CSRF check', function (string $uri) {
    $this->post($uri, [])->assertStatus(419);
})->with('posts that keep the CSRF check');

test('the CSRF exemptions are exactly the gateway and Apple callbacks, nothing wider', function () {
    expect((new ReflectionClass(PreventRequestForgery::class))->getStaticPropertyValue('neverVerify'))->toBe([
        'temp/sslcommerz*',
        'temp/payhere*',
        'temp/iyzico*',
        'temp/aamarpay*',
        'temp/bkash*',
        'temp/apple-callback',
    ]);
});
