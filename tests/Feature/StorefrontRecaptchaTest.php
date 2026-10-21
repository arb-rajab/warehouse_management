<?php

use App\Models\Shop;
use App\Models\StoreUser;
use App\Services\UserService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * reCAPTCHA on the server. With the `google_recaptcha` setting on, seller
 * registration (shops.store), a representative's create customer
 * (store_customer), and both customer registration pages show Google's
 * widget; their three POST endpoints verify its token
 * with Google (App\Rules\Recaptcha) using the secret in
 * services.recaptcha.secret_key (CAPTCHA_SECRET). They fail closed: a missing
 * secret, a provider error or an unreachable provider refuses the form like a
 * wrong answer. With the setting off nothing changes and Google is never
 * called (store-backoffice-migration.md §48).
 */
const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

/**
 * @return array<string, array{0: string, 1: Closure(array<string, mixed>): TestResponse, 2: Closure(): bool}>
 */
function recaptchaForms(): array
{
    $forms = [
        'seller registration' => [
            '/temp/shops/create',
            fn (array $extra) => test()->from('/temp/shops/create')->post('/temp/shops', [
                'name' => 'New Seller', 'email' => 'new-seller@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
                'shop_name' => 'Amsterdam Spices', 'address' => 'Damrak 1, Amsterdam', ...$extra,
            ]),
            fn () => Shop::where('name', 'Amsterdam Spices')->exists() || StoreUser::where('email', 'new-seller@example.com')->exists(),
        ],
        'create customer' => [
            '/temp/create_customer',
            function (array $extra) {
                $representative = createStoreUser('customer', 'rep@example.com');
                $representative->forceFill(['is_rep' => true])->save();
                actingAsStoreUser($representative->fresh());

                return test()->from('/temp/create_customer')->post('/temp/store_customer', [
                    'name' => 'New Customer', 'company_name' => 'Acme BV', 'email' => 'new-customer@example.com', 'phone' => '+31612345678',
                    'country' => 'Netherlands', 'tax_number' => 'NL123456789B01', 'bank_account_number' => 'NL91ABNA0417164300',
                    'company_address' => 'Damrak 1, Amsterdam', 'shipping_address' => 'Damrak 2, Amsterdam',
                    'trade_license' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
                    'password' => 'secret123', 'password_confirmation' => 'secret123', ...$extra,
                ]);
            },
            fn () => StoreUser::where('email', 'new-customer@example.com')->exists(),
        ],
    ];

    foreach (['/temp/register', '/temp/users/registration'] as $page) {
        $forms['customer registration '.$page] = [
            $page,
            fn (array $extra) => test()->from($page)->post('/temp/register', [...completeRegistration(), ...$extra]),
            fn () => StoreUser::where('email', 'new-customer@example.com')->exists(),
        ];
    }

    return $forms;
}

beforeEach(function () {
    Storage::fake('local');
    DB::table('countries')->insert(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);
    $this->mock(UserService::class)->shouldReceive('createCustomerOnOtajer');
    config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret_key' => 'the-secret']);
});

describe('with reCAPTCHA on', function () {
    beforeEach(fn () => storeSetting('google_recaptcha', 1));

    test('a token Google accepts lets the form through, and Google was asked with the secret, the token and the visitor\'s address', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::response(['success' => true, 'hostname' => 'localhost'])]);

        $submit(['g-recaptcha-response' => 'a-valid-token'])->assertSessionHasNoErrors();

        expect($written())->toBeTrue();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === RECAPTCHA_VERIFY_URL
            && $request->method() === 'POST'
            && $request->isForm()
            && $request['secret'] === 'the-secret'
            && $request['response'] === 'a-valid-token'
            && $request['remoteip'] === '127.0.0.1');
    })->with(recaptchaForms());

    test('a token Google refuses is refused, under the widget, and nothing is written', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        // The error is read off the page: reading the session first starts it
        // in the test, and the next request then no longer sees the flash.
        $submit(['g-recaptcha-response' => 'a-forged-token'])->assertRedirect($page);

        expect($written())->toBeFalse();

        $xpath = htmlXPath($this->get($page)->assertOk());
        expect(trim($xpath->query('//*[contains(@class, "g-recaptcha")]/following-sibling::*[@role="alert"]')->item(0)?->textContent ?? ''))
            ->toBe('Please confirm that you are not a robot.');
    })->with(recaptchaForms());

    test('a missing token is refused without asking Google', function (string $page, Closure $submit, Closure $written) {
        Http::fake();

        $submit([])->assertSessionHasErrors(['g-recaptcha-response' => 'Please confirm that you are not a robot.']);

        expect($written())->toBeFalse();
        Http::assertNothingSent();
    })->with(recaptchaForms());

    test('a token that is not text is refused without asking Google', function (string $page, Closure $submit, Closure $written) {
        Http::fake();

        $submit(['g-recaptcha-response' => ['a-valid-token']])->assertSessionHasErrors(['g-recaptcha-response']);

        expect($written())->toBeFalse();
        Http::assertNothingSent();
    })->with(recaptchaForms());

    test('fails closed when Google answers with an error', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::response('Service Unavailable', 503)]);
        Log::spy();

        $submit(['g-recaptcha-response' => 'a-valid-token'])
            ->assertSessionHasErrors(['g-recaptcha-response' => 'The robot check could not be completed. Please try again.']);

        expect($written())->toBeFalse();
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context === ['status' => 503]);
    })->with(recaptchaForms());

    test('fails closed when Google cannot be reached', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::failedConnection()]);

        $submit(['g-recaptcha-response' => 'a-valid-token'])
            ->assertSessionHasErrors(['g-recaptcha-response' => 'The robot check could not be completed. Please try again.']);

        expect($written())->toBeFalse();
    })->with(recaptchaForms());

    test('fails closed when Google answers something that is not a verdict', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::response('<html>captive portal</html>')]);

        $submit(['g-recaptcha-response' => 'a-valid-token'])->assertSessionHasErrors(['g-recaptcha-response']);

        expect($written())->toBeFalse();
    })->with(recaptchaForms());

    test('fails closed, without asking Google or logging a secret, when no secret is configured', function (string $page, Closure $submit, Closure $written) {
        config(['services.recaptcha.secret_key' => null]);
        Http::fake();
        Log::spy();

        $submit(['g-recaptcha-response' => 'a-valid-token'])
            ->assertSessionHasErrors(['g-recaptcha-response' => 'The robot check could not be completed. Please try again.']);

        expect($written())->toBeFalse();
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context = []) => str_contains($message, 'CAPTCHA_SECRET') && $context === []);
    })->with(recaptchaForms());

    test('never writes the secret or the token to the log', function (string $page, Closure $submit, Closure $written) {
        Http::fake([RECAPTCHA_VERIFY_URL => Http::response('error', 500)]);
        Log::spy();

        $submit(['g-recaptcha-response' => 'a-valid-token']);

        Log::shouldNotHaveReceived('warning', fn (string $message, array $context = []) => str_contains($message.json_encode($context), 'the-secret')
            || str_contains($message.json_encode($context), 'a-valid-token'));
    })->with(recaptchaForms());
});

describe('with reCAPTCHA off', function () {
    test('the form goes through without a token and Google is never asked', function (string $page, Closure $submit, Closure $written) {
        storeSetting('google_recaptcha', 0);
        Http::fake();

        $submit([])->assertSessionHasNoErrors();

        expect($written())->toBeTrue();
        Http::assertNothingSent();
    })->with(recaptchaForms());

    test('a stray token is ignored', function (string $page, Closure $submit, Closure $written) {
        Http::fake();

        $submit(['g-recaptcha-response' => ['anything']])->assertSessionHasNoErrors();

        expect($written())->toBeTrue();
        Http::assertNothingSent();
    })->with(recaptchaForms());
});
