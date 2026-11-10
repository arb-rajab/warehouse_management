<?php

use App\Http\Controllers\Payment\BkashController;
use App\Http\Controllers\SSLCommerz;
use App\Utility\NagadUtility;
use App\Utility\NgeniusUtility;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/**
 * Production runs `artisan config:cache`, after which env() outside config/
 * returns null. SSLCommerz, bKash, Nagad, AamarPay, N-Genius and VoguePay read
 * their credentials with env(), so on the live site every one of them was
 * sending empty keys. They are read with config() now, from config/services.php
 * and config/nagad.php.
 */
test('no gateway code reads its credentials with env()', function () {
    $pattern = '/\benv\(\s*[\'"](?:SSLCZ_|BKASH_|AAMARPAY_|NGENIUS_|NAGAD_|VOGUE_|PAYHERE_|IYZICO_)/';
    $offenders = [];

    foreach (sourceFilesUnder(['app', 'resources/views']) as $path => $source) {
        if (preg_match($pattern, $source, $match)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('every gateway credential has a config entry fed by an environment variable', function () {
    $config = file_get_contents(config_path('services.php')).file_get_contents(config_path('nagad.php'));

    foreach (['SSLCZ_STORE_ID', 'SSLCZ_STORE_PASSWD', 'BKASH_CHECKOUT_APP_KEY', 'BKASH_CHECKOUT_APP_SECRET', 'BKASH_CHECKOUT_USER_NAME', 'BKASH_CHECKOUT_PASSWORD',
        'AAMARPAY_STORE_ID', 'AAMARPAY_SIGNATURE_KEY', 'NGENIUS_API_KEY', 'NGENIUS_OUTLET_ID', 'VOGUE_MERCHANT_ID', 'NAGAD_PG_PUBLIC_KEY', 'NAGAD_MERCHANT_PRIVATE_KEY'] as $variable) {
        expect($config)->toContain("env('$variable')");
    }
});

test('SSLCommerz takes the store id and password from config', function () {
    config(['services.sslcommerz.store_id' => 'cfg-store', 'services.sslcommerz.store_password' => 'cfg-pass']);
    session(['payment_type' => 'cart_payment']);

    $gateway = new SSLCommerz;

    expect((fn () => [$this->store_id, $this->store_pass])->call($gateway))->toBe(['cfg-store', 'cfg-pass']);
});

test('bKash asks for its token with the configured username, password, app key and secret', function () {
    config(['services.bkash.username' => 'cfg-user', 'services.bkash.password' => 'cfg-pass', 'services.bkash.app_key' => 'cfg-key', 'services.bkash.app_secret' => 'cfg-secret']);
    Http::fake(['*checkout/token/grant' => Http::response(['id_token' => 'TOKEN'])]);

    expect((new BkashController)->getToken())->toBe('TOKEN');

    Http::assertSent(fn (ClientRequest $request) => $request->header('username') === ['cfg-user']
        && $request->header('password') === ['cfg-pass']
        && $request['app_key'] === 'cfg-key'
        && $request['app_secret'] === 'cfg-secret');
});

test('N-Genius asks for its access token with the configured API key', function () {
    config(['services.ngenius.api_key' => 'cfg-api-key']);
    Http::fake(['*' => Http::response(['access_token' => 'ACCESS'])]);

    expect(NgeniusUtility::getAccessToken())->toBe('ACCESS');

    Http::assertSent(fn (ClientRequest $request) => $request->header('Authorization') === ['Basic cfg-api-key']);
});

test('N-Genius reads an order at the configured outlet', function () {
    config(['services.ngenius.api_key' => 'k', 'services.ngenius.outlet_id' => 'OUTLET-9']);
    Http::fake(['*' => Http::response(['access_token' => 'ACCESS', 'reference' => 'REF-1'])]);

    NgeniusUtility::retrieve_order('REF-1');

    Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/transactions/outlets/OUTLET-9/orders/REF-1'));
});

test('Nagad encrypts with the configured payment gateway public key', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privatePem);
    $publicPem = openssl_pkey_get_details($key)['key'];
    config(['nagad.pg_public_key' => trim(str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $publicPem))]);

    $encrypted = NagadUtility::EncryptDataWithPublicKey('sensitive data');
    openssl_private_decrypt(base64_decode($encrypted), $decrypted, $privatePem);

    expect($decrypted)->toBe('sensitive data');
});
