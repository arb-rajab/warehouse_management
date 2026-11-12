<?php

use App\Http\Controllers\Api\V1\PaymentTypesController;
use App\Http\Controllers\Payment\BkashController;
use App\Http\Controllers\SSLCommerz;
use App\Models\BusinessSetting;
use App\Models\PaymentGatewaySetting;
use App\Services\GatewayCurrency;
use App\Services\PaymentOptions;
use App\Support\StorePaymentGateways;
use App\Utility\NagadUtility;
use App\Utility\NgeniusUtility;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Payment methods page saves every payment gateway's credentials,
 * currency, mode and switch over the server's `.env`
 * (.ai/rules/admin-env-overrides.md): per gateway as one group, secrets
 * encrypted and never sent back, the enabled list chosen here or followed
 * from STORE_PAYMENT_GATEWAYS. The security side (current password, audit,
 * notification) is in PaymentGatewaySettingsSecurityTest.
 */

const PGS_BKASH_SECRET = 'admin-bkash-app-secret';
const PGS_BKASH_PASSWORD = 'admin-bkash-password';
const PGS_ENV_SECRET = 'env-bkash-app-secret';

beforeEach(function () {
    Mail::fake();
    config([
        'store.enabled_payment_gateways' => [],
        'services.bkash.app_key' => 'ENVKEY', 'services.bkash.app_secret' => PGS_ENV_SECRET,
        'services.bkash.username' => 'env-user', 'services.bkash.password' => 'env-password', 'services.bkash.currency' => 'BDT',
    ]);
});

/**
 * The form as the page posts it: the enabled-list source, every gateway with
 * the mode the page preselects (its effective one) and the given fields, and
 * the current password.
 *
 * @param  array<string, array<string, mixed>>  $gateways
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pgsForm(array $gateways = [], array $overrides = []): array
{
    $form = ['enabled_source' => StorePaymentGateways::enabledSource(), 'gateways' => [], 'current_password' => 'correct-password'];

    foreach (StorePaymentGateways::GATEWAYS as $gateway => $spec) {
        $form['gateways'][$gateway] = isset($spec['mode']['setting']) ? ['mode' => StorePaymentGateways::mode($gateway)] : [];
    }

    foreach ($gateways as $gateway => $fields) {
        $form['gateways'][$gateway] = [...$form['gateways'][$gateway], ...$fields];
    }

    return array_replace($form, $overrides);
}

/** @return array<string, string> a complete bKash group */
function pgsBkash(array $overrides = []): array
{
    return [
        'app_key' => 'ADMINKEY', 'app_secret' => PGS_BKASH_SECRET, 'username' => 'admin-user',
        'password' => PGS_BKASH_PASSWORD, 'currency' => 'BDT', ...$overrides,
    ];
}

/** @return array<string, string> field => stored value of one gateway */
function pgsRows(string $gateway): array
{
    return DB::table('payment_gateway_settings')->where('gateway', $gateway)->pluck('value', 'field')->all();
}

function pgsAdmin(): void
{
    actingAsStoreUser(createStoreAdmin());
}

// saving and precedence

test('a gateway saved in the admin wins over .env as one group, its secrets encrypted at rest', function () {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))
        ->assertRedirect(route('payment_method.index'))
        ->assertInertiaFlash('success', __('store_admin.payment_method_settings_have_been_updated_successfully'));

    $rows = pgsRows('bkash');
    expect(array_keys($rows))->toEqualCanonicalizing(['app_key', 'app_secret', 'username', 'password', 'currency'])
        ->and($rows['app_key'])->toBe('ADMINKEY')
        ->and($rows['app_secret'])->not->toBe(PGS_BKASH_SECRET)
        ->and(Crypt::decryptString($rows['app_secret']))->toBe(PGS_BKASH_SECRET)
        ->and(Crypt::decryptString($rows['password']))->toBe(PGS_BKASH_PASSWORD)
        ->and(DB::table('payment_gateway_settings')->where('field', 'app_secret')->value('is_secret'))->toBeTruthy()
        ->and(DB::table('payment_gateway_settings')->where('field', 'app_key')->value('updated_by'))->toBe(auth('store')->id());

    expect(StorePaymentGateways::usesAdmin('bkash'))->toBeTrue()
        ->and(StorePaymentGateways::get('bkash', 'app_key'))->toBe('ADMINKEY')
        ->and(StorePaymentGateways::get('bkash', 'app_secret'))->toBe(PGS_BKASH_SECRET)
        ->and(StorePaymentGateways::get('bkash', 'username'))->toBe('admin-user')
        ->and(StorePaymentGateways::get('bkash', 'password'))->toBe(PGS_BKASH_PASSWORD);
});

test('without a saved group every field of the gateway comes from .env', function () {
    expect(StorePaymentGateways::usesAdmin('bkash'))->toBeFalse()
        ->and(StorePaymentGateways::get('bkash', 'app_key'))->toBe('ENVKEY')
        ->and(StorePaymentGateways::get('bkash', 'app_secret'))->toBe(PGS_ENV_SECRET)
        ->and(StorePaymentGateways::effective('bkash')['app_key'])->toBe(['value' => 'ENVKEY', 'source' => 'env'])
        ->and(StorePaymentGateways::effective('bkash')['app_secret'])->toBe(['set' => true, 'source' => 'env']);
});

test('a saved group never mixes in a .env value: a field it lacks is empty, not the server\'s', function () {
    PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'app_key', 'value' => 'ADMINKEY']);
    StorePaymentGateways::forget();

    expect(StorePaymentGateways::get('bkash', 'app_secret'))->toBeNull()
        ->and(StorePaymentGateways::get('bkash', 'password'))->toBeNull()
        ->and(StorePaymentGateways::missing('bkash'))->toBe(['app_secret', 'username', 'password', 'currency']);
});

test('emptying the identifying field removes the whole group, secrets included, and .env applies again', function () {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => ['app_key' => '', 'app_secret' => '', 'username' => '', 'password' => '', 'currency' => '']]))
        ->assertSessionHasNoErrors();

    expect(pgsRows('bkash'))->toBe([])
        ->and(StorePaymentGateways::get('bkash', 'app_secret'))->toBe(PGS_ENV_SECRET);
});

test('an empty secret keeps the saved one, and a typed one replaces it', function () {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash(['app_secret' => '', 'password' => ''])]))->assertSessionHasNoErrors();
    expect(StorePaymentGateways::get('bkash', 'app_secret'))->toBe(PGS_BKASH_SECRET);

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash(['app_secret' => 'a-new-secret', 'password' => ''])]))->assertSessionHasNoErrors();
    expect(StorePaymentGateways::get('bkash', 'app_secret'))->toBe('a-new-secret')
        ->and(StorePaymentGateways::get('bkash', 'password'))->toBe(PGS_BKASH_PASSWORD);
});

test('a saved secret is never kept for another account: a new app key or username asks for it again', function (array $change) {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash([...$change, 'app_secret' => '', 'password' => ''])]))
        ->assertSessionHasErrors(['gateways.bkash.app_secret', 'gateways.bkash.password']);

    expect(pgsRows('bkash')['app_key'])->toBe('ADMINKEY');
})->with([
    'another app key' => [['app_key' => 'OTHERKEY']],
    'another username' => [['username' => 'someone-else']],
]);

test('removing a required saved secret while the group stays is refused; a new value with "remove" ticked too', function () {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash(['app_secret' => '', 'password' => '', 'app_secret_clear' => '1'])]))
        ->assertSessionHasErrors(['gateways.bkash.app_secret' => __('validation.required', ['attribute' => __('validation.attributes.gateways.bkash.app_secret')])]);

    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash(['app_secret_clear' => '1'])]))
        ->assertSessionHasErrors('gateways.bkash.app_secret');

    expect(StorePaymentGateways::get('bkash', 'app_secret'))->toBe(PGS_BKASH_SECRET);
});

test('a group needs every required field once one is typed, and a secret only when none is saved', function () {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm(['bkash' => ['app_key' => 'ADMINKEY']]))
        ->assertSessionHasErrors(['gateways.bkash.username', 'gateways.bkash.currency', 'gateways.bkash.app_secret', 'gateways.bkash.password']);
    $this->post(route('payment_method.update'), pgsForm(['bkash' => ['username' => 'admin-user']]))
        ->assertSessionHasErrors(['gateways.bkash.app_key']);

    expect(pgsRows('bkash'))->toBe([]);
});

test('each field keeps to the format the gateway uses', function (string $gateway, array $fields, string $error) {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm([$gateway => $fields]))->assertSessionHasErrors($error);
    expect(pgsRows($gateway))->toBe([]);
})->with([
    'bKash app key with a space' => ['bkash', pgsBkash(['app_key' => 'ADMIN KEY']), 'gateways.bkash.app_key'],
    'a secret with a line break' => ['bkash', pgsBkash(['app_secret' => "secret\nline"]), 'gateways.bkash.app_secret'],
    'a currency that is not three capitals' => ['bkash', pgsBkash(['currency' => 'TAKA']), 'gateways.bkash.currency'],
    'a Nagad merchant number that is not a phone' => ['nagad', ['merchant_id' => '683002007104225', 'merchant_number' => '12345', 'pg_public_key' => 'QUJD', 'merchant_private_key' => 'REVG', 'mode' => 'sandbox'], 'gateways.nagad.merchant_number'],
    'an N-Genius outlet that is not a UUID' => ['ngenius', ['outlet_id' => 'outlet-1', 'api_key' => 'YWJj', 'currency' => 'AED', 'mode' => 'sandbox'], 'gateways.ngenius.outlet_id'],
    'an unknown mode' => ['ngenius', ['outlet_id' => '0d6e5c51-1b3f-4c6c-9b4d-2f6a8f2b9c11', 'api_key' => 'YWJj', 'currency' => 'AED', 'mode' => 'production'], 'gateways.ngenius.mode'],
    'a Stripe secret that is not a key' => ['stripe', ['publishable_key' => 'pk_test_abc', 'secret_key' => 'not-a-key'], 'gateways.stripe.secret_key'],
    'a Paytm channel it does not offer' => ['paytm', ['merchant_id' => 'MID1', 'merchant_key' => 'key', 'merchant_website' => 'WEBSTAGING', 'channel' => 'APP', 'mode' => 'sandbox'], 'gateways.paytm.channel'],
]);

test('Stripe\'s keys must both be test keys or both live keys', function () {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm(['stripe' => ['publishable_key' => 'pk_test_abc', 'secret_key' => 'sk_live_def']]))
        ->assertSessionHasErrors(['gateways.stripe.secret_key' => __('store_admin.payment_gateway_stripe_keys_mismatch')]);

    $this->post(route('payment_method.update'), pgsForm(['stripe' => ['publishable_key' => 'pk_live_abc', 'secret_key' => 'sk_live_def']]))
        ->assertSessionHasNoErrors();
    expect(StorePaymentGateways::mode('stripe'))->toBe('live');
});

test('a key pasted with its PEM lines and line breaks is stored as its body', function () {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm(['nagad' => [
        'merchant_id' => '683002007104225', 'pg_public_key' => "-----BEGIN PUBLIC KEY-----\nQUJD\nREVG\n-----END PUBLIC KEY-----",
        'merchant_private_key' => "REVG\nSElK", 'mode' => 'live',
    ]]))->assertSessionHasNoErrors();

    expect(StorePaymentGateways::get('nagad', 'pg_public_key'))->toBe('QUJDREVG')
        ->and(StorePaymentGateways::get('nagad', 'merchant_private_key'))->toBe('REVGSElK')
        ->and(StorePaymentGateways::get('nagad', 'mode'))->toBe('live');
});

test('anything the form does not post is refused', function (array $payload, string $error) {
    pgsAdmin();

    $this->post(route('payment_method.update'), array_replace_recursive(pgsForm(), $payload))->assertSessionHasErrors($error);
    expect(DB::table('payment_gateway_settings')->count())->toBe(0);
})->with([
    'an unknown top-level key' => [['MAIL_PASSWORD' => 'x'], 'MAIL_PASSWORD'],
    'an unknown gateway' => [['gateways' => ['paypal' => ['client_id' => 'x']]], 'gateways.paypal'],
    'an unknown field' => [['gateways' => ['bkash' => ['callback_url' => 'https://evil.example']]], 'gateways.bkash.callback_url'],
    'Nagad\'s callback URL, which stays .env only' => [['gateways' => ['nagad' => ['callback_url' => 'https://evil.example']]], 'gateways.nagad.callback_url'],
]);

test('an unreadable saved secret reads as not set and the page asks for it again', function () {
    PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'app_key', 'value' => 'ADMINKEY']);
    PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'app_secret', 'value' => 'not-a-ciphertext', 'is_secret' => true]);
    StorePaymentGateways::forget();

    expect(StorePaymentGateways::get('bkash', 'app_secret'))->toBeNull()
        ->and(StorePaymentGateways::secretUnreadable('bkash', 'app_secret'))->toBeTrue();
});

test('a missing table reads as nothing saved', function () {
    Schema::drop('payment_gateway_settings');
    StorePaymentGateways::forget();

    expect(StorePaymentGateways::stored())->toBe([])
        ->and(StorePaymentGateways::get('bkash', 'app_key'))->toBe('ENVKEY')
        ->and(StorePaymentGateways::enabled())->toBe([]);
});

test('the six sandbox switches are saved with their gateway, as business_settings rows', function () {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm(['sslcommerz' => ['mode' => 'sandbox'], 'bkash' => ['mode' => 'live']]))
        ->assertSessionHasNoErrors();

    expect(BusinessSetting::query()->where('type', 'sslcommerz_sandbox')->value('value'))->toBe('1')
        ->and(BusinessSetting::query()->where('type', 'bkash_sandbox')->value('value'))->toBe('0')
        ->and(StorePaymentGateways::mode('sslcommerz'))->toBe('sandbox')
        ->and(StorePaymentGateways::mode('bkash'))->toBe('live');
});

// the enabled list

test('the enabled list follows STORE_PAYMENT_GATEWAYS until it is chosen here, and the choice wins', function () {
    config(['store.enabled_payment_gateways' => ['payhere', 'bkash']]);
    pgsAdmin();
    expect(StorePaymentGateways::enabledSource())->toBe('env')
        ->and(StorePaymentGateways::enabled())->toBe(['payhere', 'bkash']);

    $this->post(route('payment_method.update'), pgsForm(['bkash' => ['enabled' => '0'], 'payhere' => ['enabled' => '1'], 'nagad' => ['enabled' => '1']], ['enabled_source' => 'admin']))
        ->assertSessionHasNoErrors();

    expect(StorePaymentGateways::enabledSource())->toBe('admin')
        ->and(StorePaymentGateways::enabled())->toBe(['nagad', 'payhere'])
        ->and(DB::table('payment_gateway_settings')->where('field', 'enabled')->count())->toBe(count(StorePaymentGateways::GATEWAYS));
});

test('chosen here with every switch off, no gateway is enabled, whatever .env lists', function () {
    config(['store.enabled_payment_gateways' => ['payhere']]);
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm([], ['enabled_source' => 'admin']))->assertSessionHasNoErrors();

    expect(StorePaymentGateways::enabled())->toBe([])
        ->and($this->post(route('payhere.checkout.cancel'))->getStatusCode())->toBe(404);
});

test('following .env again removes the saved switches', function () {
    config(['store.enabled_payment_gateways' => ['payhere']]);
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => ['enabled' => '1']], ['enabled_source' => 'admin']))->assertSessionHasNoErrors();

    $this->post(route('payment_method.update'), pgsForm([], ['enabled_source' => 'env']))->assertSessionHasNoErrors();

    expect(DB::table('payment_gateway_settings')->where('field', 'enabled')->count())->toBe(0)
        ->and(StorePaymentGateways::enabled())->toBe(['payhere']);
});

test('a gateway with no payment flow cannot be enabled', function (string $gateway) {
    pgsAdmin();

    $this->post(route('payment_method.update'), pgsForm([$gateway => ['enabled' => '1']], ['enabled_source' => 'admin']))
        ->assertSessionHasErrors("gateways.{$gateway}.enabled");
    expect(StorePaymentGateways::isEnabled($gateway))->toBeFalse();
})->with(['stripe', 'paytm', 'toyyibpay']);

test('the gateway routes follow the enabled list chosen here', function () {
    config(['store.enabled_payment_gateways' => []]);
    actingAsStoreUser(createStoreUser('customer'));
    expect($this->post(route('payhere.checkout.cancel'))->getStatusCode())->toBe(404);

    PaymentGatewaySetting::query()->create(['gateway' => 'payhere', 'field' => 'enabled', 'value' => '1']);
    StorePaymentGateways::forget();

    expect($this->post(route('payhere.checkout.cancel'))->getStatusCode())->not->toBe(404)
        ->and($this->get(route('bkash.callback'))->getStatusCode())->toBe(404);
});

test('the checkout options follow the enabled list chosen here', function () {
    config(['store.enabled_payment_gateways' => ['bkash']]);
    DB::table('business_settings')->insert([['type' => 'bkash', 'value' => '1'], ['type' => 'nagad', 'value' => '1']]);
    expect(array_keys(app(PaymentOptions::class)->available()))->toBe(['bkash'])
        ->and(app(PaymentOptions::class)->isAllowed('nagad'))->toBeFalse();

    PaymentGatewaySetting::query()->create(['gateway' => 'nagad', 'field' => 'enabled', 'value' => '1']);
    PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'enabled', 'value' => '0']);
    StorePaymentGateways::forget();

    expect(array_keys(app(PaymentOptions::class)->available()))->toBe(['nagad'])
        ->and(app(PaymentOptions::class)->isAllowed('nagad'))->toBeTrue()
        ->and(app(PaymentOptions::class)->isAllowed('bkash'))->toBeFalse();
});

test('the app lists an online gateway only while it is switched on and enabled', function () {
    DB::table('business_settings')->insert([['type' => 'bkash', 'value' => '1'], ['type' => 'iyzico', 'value' => '1']]);
    $keys = fn (): array => array_column(app(PaymentTypesController::class)->options(Request::create('/', 'GET', ['list' => 'online']))['payment_types'], 'payment_type_key');

    config(['store.enabled_payment_gateways' => []]);
    expect($keys())->toBe([]);

    config(['store.enabled_payment_gateways' => ['bkash']]);
    expect($keys())->toBe(['bkash']);

    PaymentGatewaySetting::query()->create(['gateway' => 'iyzico', 'field' => 'enabled', 'value' => '1']);
    StorePaymentGateways::forget();
    expect($keys())->toBe(['iyzico']);
});

// consumers read the effective values

test('bKash asks for its token with the credentials saved in the admin, never the .env ones', function () {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();
    Http::fake(['*checkout/token/grant' => Http::response(['id_token' => 'TOKEN'])]);

    expect((new BkashController)->getToken())->toBe('TOKEN');

    Http::assertSent(fn (ClientRequest $request) => $request->header('username') === ['admin-user']
        && $request->header('password') === [PGS_BKASH_PASSWORD]
        && $request['app_key'] === 'ADMINKEY'
        && $request['app_secret'] === PGS_BKASH_SECRET);
});

test('SSLCommerz takes the store id and password saved in the admin', function () {
    PaymentGatewaySetting::query()->create(['gateway' => 'sslcommerz', 'field' => 'store_id', 'value' => 'admin-store']);
    PaymentGatewaySetting::query()->create(['gateway' => 'sslcommerz', 'field' => 'store_password', 'value' => Crypt::encryptString('admin-pass'), 'is_secret' => true]);
    StorePaymentGateways::forget();
    config(['services.sslcommerz.store_id' => 'env-store', 'services.sslcommerz.store_password' => 'env-pass']);
    session(['payment_type' => 'cart_payment']);

    $gateway = new SSLCommerz;

    expect((fn () => [$this->store_id, $this->store_pass])->call($gateway))->toBe(['admin-store', 'admin-pass']);
});

test('N-Genius uses the outlet, key and mode saved in the admin', function () {
    PaymentGatewaySetting::query()->insert([
        ['gateway' => 'ngenius', 'field' => 'outlet_id', 'value' => '0d6e5c51-1b3f-4c6c-9b4d-2f6a8f2b9c11', 'is_secret' => false],
        ['gateway' => 'ngenius', 'field' => 'api_key', 'value' => Crypt::encryptString('admin-api-key'), 'is_secret' => true],
        ['gateway' => 'ngenius', 'field' => 'mode', 'value' => 'live', 'is_secret' => false],
        ['gateway' => 'ngenius', 'field' => 'currency', 'value' => 'EUR', 'is_secret' => false],
    ]);
    StorePaymentGateways::forget();
    config(['services.ngenius.api_key' => 'env-key', 'services.ngenius.mode' => 'uat', 'services.ngenius.currency' => 'AED']);
    Http::fake(['*' => Http::response(['access_token' => 'ACCESS'])]);

    expect(NgeniusUtility::getMode())->toBe('live')
        ->and(NgeniusUtility::getAccessToken())->toBe('ACCESS')
        ->and(GatewayCurrency::forGateway('ngenius')->code)->toBe('EUR');
    Http::assertSent(fn (ClientRequest $request) => $request->header('Authorization') === ['Basic admin-api-key']);
});

test('Nagad encrypts with the public key saved in the admin', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privatePem);
    $publicPem = openssl_pkey_get_details($key)['key'];
    pgsAdmin();
    config(['nagad.pg_public_key' => 'not-the-key']);

    $this->post(route('payment_method.update'), pgsForm(['nagad' => [
        'merchant_id' => '683002007104225', 'pg_public_key' => $publicPem, 'merchant_private_key' => 'REVG', 'mode' => 'sandbox',
    ]]))->assertSessionHasNoErrors();

    openssl_private_decrypt(base64_decode(NagadUtility::EncryptDataWithPublicKey('sensitive data')), $decrypted, $privatePem);

    expect($decrypted)->toBe('sensitive data');
});

test('the gateway currency and PayHere\'s merchant id and secret come from the admin', function () {
    PaymentGatewaySetting::query()->insert([
        ['gateway' => 'payhere', 'field' => 'merchant_id', 'value' => '1211149', 'is_secret' => false],
        ['gateway' => 'payhere', 'field' => 'secret', 'value' => Crypt::encryptString('admin-secret'), 'is_secret' => true],
        ['gateway' => 'payhere', 'field' => 'currency', 'value' => 'LKR', 'is_secret' => false],
    ]);
    StorePaymentGateways::forget();
    config(['services.payhere.merchant_id' => '999', 'services.payhere.secret' => 'env-secret', 'services.payhere.currency' => 'USD']);

    expect(GatewayCurrency::forGateway('payhere')->code)->toBe('LKR')
        ->and(StorePaymentGateways::get('payhere', 'merchant_id'))->toBe('1211149')
        ->and(StorePaymentGateways::get('payhere', 'secret'))->toBe('admin-secret');

    foreach (['checkout_form', 'wallet_form', 'customer_package_form'] as $form) {
        expect(file_get_contents(resource_path("views/shop/frontend/payhere/{$form}.blade.php")))
            ->toContain("StorePaymentGateways::get('payhere', 'merchant_id')");
    }
});

test('no gateway code reads a gateway credential from config directly any more', function () {
    $pattern = "/config\\(\\s*['\"](?:services\\.(?:bkash|sslcommerz|iyzico|payhere|aamarpay|voguepay)\\.(?!currency|identity_number)|services\\.ngenius\\.(?:api_key|outlet_id|mode|currency)|nagad\\.(?!callback_url))/";
    $offenders = [];

    foreach (sourceFilesUnder(['app', 'resources/views']) as $path => $source) {
        if (str_ends_with($path, 'StorePaymentGateways.php')) {
            continue;
        }
        if (preg_match($pattern, $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

// the page

test('the page shows each gateway\'s saved and effective values and never a secret', function () {
    config(['services.payhere.merchant_id' => '1211149', 'services.payhere.secret' => 'env-payhere-secret']);
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $response = $this->get(route('payment_method.index'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Payments')
        ->where('payments.enabledSource', 'env')
        ->where('payments.gateways', function ($gateways): bool {
            $gateways = collect($gateways)->keyBy('key');
            $bkash = $gateways['bkash'];
            $fields = collect($bkash['fields'])->keyBy('key');

            return $gateways->keys()->all() === array_keys(StorePaymentGateways::GATEWAYS)
                && $bkash['source'] === 'admin'
                && $fields['app_key']['value'] === 'ADMINKEY'
                && $fields['app_secret']['value'] === null
                && $fields['app_secret']['set'] === true
                && $fields['app_secret']['environment'] === null
                && $bkash['effective']['app_secret'] === ['set' => true, 'source' => 'admin']
                && $gateways['payhere']['source'] === 'env'
                && $gateways['payhere']['effective']['merchant_id'] === ['value' => '1211149', 'source' => 'env']
                && $gateways['payhere']['effective']['secret'] === ['set' => true, 'source' => 'env']
                && $gateways['stripe']['flow'] === false;
        }));

    $html = $response->getContent();
    expect($html)->not->toContain(PGS_BKASH_SECRET)->not->toContain(PGS_BKASH_PASSWORD)
        ->not->toContain('env-payhere-secret')->not->toContain(PGS_ENV_SECRET)
        ->not->toContain(DB::table('payment_gateway_settings')->where('field', 'app_secret')->value('value'));
});

test('an enabled gateway that lacks a required field is named on the page', function () {
    config(['store.enabled_payment_gateways' => ['bkash'], 'services.bkash.app_secret' => null]);
    pgsAdmin();

    $this->get(route('payment_method.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('payments.gateways.0.key', 'bkash')
        ->where('payments.gateways.0.enabled', true)
        ->where('payments.gateways.0.missing', ['app_secret']));
});

test('iyzico\'s identity number reaches the page as set or not set, never as a value', function (?string $number, bool $set) {
    config(['services.iyzico.identity_number' => $number]);
    pgsAdmin();

    $response = $this->get(route('payment_method.index'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->where('payments.gateways', function ($gateways) use ($set): bool {
        $gateways = collect($gateways)->keyBy('key');

        return $gateways['iyzico']['env_only'] === [['key' => 'identity_number', 'env' => 'IYZICO_IDENTITY_NUMBER', 'set' => $set]]
            && $gateways->except('iyzico')->every(fn (array $gateway): bool => $gateway['env_only'] === []);
    }));
    expect($response->getContent())->not->toContain('12345678901');
})->with([
    'unset' => [null, false],
    'blank' => ['   ', false],
    'set' => ['12345678901', true],
]);

test('the unconfirmed-live note is sent only for N-Genius and AamarPay, enabled, in live mode', function () {
    config([
        'store.enabled_payment_gateways' => ['ngenius', 'aamarpay', 'bkash'],
        'services.ngenius.mode' => 'live',
    ]);
    pgsAdmin();
    $flagged = fn (array $keys) => $this->get(route('payment_method.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('payments.gateways', fn ($gateways): bool => collect($gateways)
            ->filter(fn (array $gateway): bool => $gateway['live_unconfirmed'])->pluck('key')->sort()->values()->all() === $keys),
    );

    // AamarPay has no saved sandbox switch, so it is live; bKash is never flagged.
    $flagged(['aamarpay', 'ngenius']);

    // Sandbox mode switches the note off, per gateway.
    config(['services.ngenius.mode' => 'uat']);
    $flagged(['aamarpay']);
    DB::table('business_settings')->insert(['type' => 'aamarpay_sandbox', 'value' => '1']);
    $flagged([]);

    // Live again, but a disabled gateway is not flagged.
    DB::table('business_settings')->where('type', 'aamarpay_sandbox')->update(['value' => '0']);
    config(['services.ngenius.mode' => 'live', 'store.enabled_payment_gateways' => ['bkash']]);
    $flagged([]);
});

test('StorePaymentGateways reports the .env-only values a gateway lacks and when its live setup is unconfirmed', function () {
    config(['services.iyzico.identity_number' => null, 'store.enabled_payment_gateways' => ['aamarpay']]);

    expect(StorePaymentGateways::envOnlyMissing('iyzico'))->toBe(['identity_number'])
        ->and(StorePaymentGateways::envOnlyMissing('bkash'))->toBe([])
        ->and(StorePaymentGateways::liveUnconfirmed('aamarpay'))->toBeTrue()
        ->and(StorePaymentGateways::liveUnconfirmed('ngenius'))->toBeFalse()
        ->and(StorePaymentGateways::liveUnconfirmed('bkash'))->toBeFalse();

    config(['services.iyzico.identity_number' => '12345678901']);
    expect(StorePaymentGateways::envOnlyMissing('iyzico'))->toBe([]);
});

test('the page passes on only a highlight key it holds', function (mixed $highlight, ?string $expected) {
    pgsAdmin();

    $this->get(route('payment_method.index', ['highlight' => $highlight]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('highlight', $expected));
})->with([
    'a gateway field' => ['bkash.app_secret', 'bkash.app_secret'],
    'a switch' => ['nagad.enabled', 'nagad.enabled'],
    'the list source' => ['enabled_source', 'enabled_source'],
    'a sandbox switch' => ['bkash_sandbox', 'bkash_sandbox'],
    'an .env-only value' => ['iyzico.identity_number', 'iyzico.identity_number'],
    'unknown' => ['bkash.callback_url', null],
    'an array' => [['bkash.app_key'], null],
]);

test('only staff holding payment_methods_configurations may open or save the page', function () {
    actingAsStoreUser(createStoreStaff('features_activation'));
    $this->get(route('payment_method.index'))->assertForbidden();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertForbidden();
    expect(DB::table('payment_gateway_settings')->count())->toBe(0);

    actingAsStoreUser(createStoreStaff('payment_methods_configurations'));
    $this->get(route('payment_method.index'))->assertOk();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();
    expect(pgsRows('bkash'))->toHaveKey('app_key');
});

test('WMS authentication alone never reaches the page', function () {
    actingAsAdmin();

    $this->get(route('payment_method.index'))->assertRedirectToRoute('shop.login');
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertRedirectToRoute('shop.login');
    expect(DB::table('payment_gateway_settings')->count())->toBe(0);
});

test('the public business-settings endpoint cannot read a gateway setting', function () {
    pgsAdmin();
    $this->post(route('payment_method.update'), pgsForm(['bkash' => pgsBkash()]))->assertSessionHasNoErrors();

    $this->postJson('/api/v1/store/business-settings', ['keys' => 'app_key,app_secret,bkash_app_secret,payment_gateway_settings'])
        ->assertDontSee('ADMINKEY')->assertDontSee(PGS_BKASH_SECRET);
});
