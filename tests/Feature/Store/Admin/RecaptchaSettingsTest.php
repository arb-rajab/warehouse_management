<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Rules\Recaptcha;
use App\Support\StoreRecaptchaSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The reCAPTCHA settings page edits the site key and secret that used to live
 * only in `.env` (StoreRecaptchaSettings, .ai/rules/admin-env-overrides.md): the
 * saved pair wins, the pair is saved and read as one, the secret is stored
 * encrypted and never sent back, and nothing is written into the config.
 */

const RECAPTCHA_ADMIN_KEY = '6LeAdminKeyAbCdEfGhIjKlMnOpQrStUvWx';
const RECAPTCHA_ADMIN_SECRET = 'S3cret-admin-recaptcha-secret-value';
const RECAPTCHA_ENV_KEY = '6LeEnvKeyAbCdEfGhIjKlMnOpQrStUvWxYz';
const RECAPTCHA_ENV_SECRET = 'env-recaptcha-secret-value-123456';

beforeEach(function () {
    config(['services.recaptcha.site_key' => RECAPTCHA_ENV_KEY, 'services.recaptcha.secret_key' => RECAPTCHA_ENV_SECRET]);
    Cache::forget('business_settings');
});

/** @return array<string, mixed> */
function recaptchaForm(array $overrides = []): array
{
    return [
        'settings' => ['google_recaptcha' => 1],
        'recaptcha_site_key' => RECAPTCHA_ADMIN_KEY,
        'recaptcha_secret_key' => RECAPTCHA_ADMIN_SECRET,
        ...$overrides,
    ];
}

/** The saved `business_settings` rows of the pair, by type. */
function recaptchaRows(): array
{
    return DB::table('business_settings')->whereIn('type', array_values(StoreRecaptchaSettings::KEYS))->pluck('value', 'type')->all();
}

function saveRecaptcha(array $overrides = []): void
{
    test()->post(route('google_recaptcha.update'), recaptchaForm($overrides))->assertSessionHasNoErrors()->assertRedirectToRoute('google_recaptcha.index');
}

function recaptchaSettings(): StoreRecaptchaSettings
{
    return new StoreRecaptchaSettings;
}

// saving

test('the page saves the switch and the pair and flashes its success message', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));

    $this->post(route('google_recaptcha.update'), recaptchaForm())
        ->assertRedirectToRoute('google_recaptcha.index')
        ->assertInertiaFlash('success', __('store_admin.google_recaptcha_settings_have_been_updated_successfully'));

    expect(recaptchaRows())->toHaveKey('recaptcha_site_key', RECAPTCHA_ADMIN_KEY)->toHaveKey('recaptcha_secret_key_encrypted')
        ->and(DB::table('business_settings')->whereIn('type', array_values(StoreRecaptchaSettings::KEYS))->whereNotNull('lang')->exists())->toBeFalse();
    $this->assertDatabaseHas('business_settings', ['type' => 'google_recaptcha', 'value' => '1']);
});

test('a post with only the switch leaves the saved pair alone', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();
    $before = recaptchaRows();

    $this->post(route('google_recaptcha.update'), ['settings' => ['google_recaptcha' => 0]])->assertSessionHasNoErrors();

    expect(recaptchaRows())->toBe($before);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_recaptcha', 'value' => '0']);
});

test('the secret is stored encrypted, never in plain text', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    $stored = recaptchaRows()['recaptcha_secret_key_encrypted'];

    expect($stored)->not->toContain(RECAPTCHA_ADMIN_SECRET)
        ->and(Crypt::decryptString($stored))->toBe(RECAPTCHA_ADMIN_SECRET)
        ->and(serialize(Cache::get(StoreRecaptchaSettings::CACHE_KEY)))->not->toContain(RECAPTCHA_ADMIN_SECRET)
        ->and(DB::table('business_settings')->where('value', 'like', '%'.RECAPTCHA_ADMIN_SECRET.'%')->exists())->toBeFalse();
});

test('the secret never reaches the page, its props, the flash or the errors', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    $this->get(route('google_recaptcha.index'))->assertOk()
        ->assertDontSee(RECAPTCHA_ADMIN_SECRET)->assertDontSee(RECAPTCHA_ENV_SECRET)
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Recaptcha')
            ->where('recaptcha', ['site_key' => RECAPTCHA_ADMIN_KEY, 'secret_set' => true, 'secret_unreadable' => false])
            ->where('effective', ['site_key' => ['value' => RECAPTCHA_ADMIN_KEY, 'source' => 'admin'], 'secret' => ['set' => true, 'source' => 'admin']]));

    // A refused save does not echo the secret back, in the errors or the old input.
    $this->post(route('google_recaptcha.update'), recaptchaForm(['recaptcha_site_key' => 'bad key']))
        ->assertSessionHasErrors('recaptcha_site_key');
    expect(json_encode(session('errors')?->getBag('default')->toArray()))->not->toContain(RECAPTCHA_ADMIN_SECRET)
        ->and(session()->getOldInput('recaptcha_secret_key'))->toBeNull();
});

test('the saved secret is never logged', function () {
    Log::spy();
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();
    Http::fake([Recaptcha::VERIFY_URL => Http::response(['success' => false])]);

    validator(['g-recaptcha-response' => 'token'], ['g-recaptcha-response' => [new Recaptcha]])->fails();

    Log::shouldNotHaveReceived('warning', fn (string $message, array $context = []) => str_contains($message.json_encode($context), RECAPTCHA_ADMIN_SECRET));
});

// keep, change, clear

test('an empty secret keeps the saved one for the same site key', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();
    $stored = recaptchaRows()['recaptcha_secret_key_encrypted'];

    saveRecaptcha(['recaptcha_secret_key' => '']);

    expect(recaptchaRows()['recaptcha_secret_key_encrypted'])->toBe($stored)
        ->and(recaptchaSettings()->secret())->toBe(RECAPTCHA_ADMIN_SECRET);
});

test('a new secret replaces the saved one', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    saveRecaptcha(['recaptcha_secret_key' => 'another-secret-value-1234567']);

    expect(recaptchaSettings()->secret())->toBe('another-secret-value-1234567');
});

test('a changed site key needs its secret again, and the saved secret is not kept for it', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();
    $before = recaptchaRows();

    $this->post(route('google_recaptcha.update'), recaptchaForm(['recaptcha_site_key' => '6LeOtherKeyAbCdEfGhIjKlMnOpQrStUv', 'recaptcha_secret_key' => '']))
        ->assertSessionHasErrors(['recaptcha_secret_key' => __('store_admin.recaptcha_secret_must_be_entered')]);

    expect(recaptchaRows())->toBe($before);

    saveRecaptcha(['recaptcha_site_key' => '6LeOtherKeyAbCdEfGhIjKlMnOpQrStUv', 'recaptcha_secret_key' => 'the-secret-of-the-other-key-123']);
    expect(recaptchaSettings()->siteKey())->toBe('6LeOtherKeyAbCdEfGhIjKlMnOpQrStUv')
        ->and(recaptchaSettings()->secret())->toBe('the-secret-of-the-other-key-123');
});

test('a first site key needs a secret too', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));

    $this->post(route('google_recaptcha.update'), recaptchaForm(['recaptcha_secret_key' => '']))
        ->assertSessionHasErrors(['recaptcha_secret_key' => __('store_admin.recaptcha_secret_must_be_entered')]);

    expect(recaptchaRows())->toBe([]);
});

test('an empty site key removes the pair, the secret included, so .env applies again', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    saveRecaptcha(['recaptcha_site_key' => '', 'recaptcha_secret_key' => '']);

    expect(recaptchaRows())->toBe([])
        ->and(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ENV_KEY)
        ->and(recaptchaSettings()->secret())->toBe(RECAPTCHA_ENV_SECRET);
});

test('"remove the saved secret" deletes only the secret, and the pair then fails closed', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    saveRecaptcha(['recaptcha_secret_key' => '', 'recaptcha_secret_clear' => 1]);

    expect(array_keys(recaptchaRows()))->toBe(['recaptcha_site_key'])
        ->and(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ADMIN_KEY)
        ->and(recaptchaSettings()->secret())->toBeNull();
});

test('a new secret together with "remove the saved secret" is refused', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    $this->post(route('google_recaptcha.update'), recaptchaForm(['recaptcha_secret_clear' => 1]))->assertSessionHasErrors('recaptcha_secret_key');
});

// precedence

test('with nothing saved both values come from .env', function () {
    expect(recaptchaSettings()->effective())->toBe([
        'site_key' => ['value' => RECAPTCHA_ENV_KEY, 'source' => 'env'],
        'secret' => ['set' => true, 'source' => 'env'],
    ])->and(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ENV_KEY)
        ->and(recaptchaSettings()->secret())->toBe(RECAPTCHA_ENV_SECRET);
});

test('with a site key saved both values come from the admin, and never mix with .env', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    expect(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ADMIN_KEY)
        ->and(recaptchaSettings()->secret())->toBe(RECAPTCHA_ADMIN_SECRET)
        ->and(recaptchaSettings()->effective()['secret'])->toBe(['set' => true, 'source' => 'admin']);

    // A saved site key whose secret is gone does not fall back to CAPTCHA_SECRET.
    DB::table('business_settings')->where('type', 'recaptcha_secret_key_encrypted')->delete();
    Cache::forget(StoreRecaptchaSettings::CACHE_KEY);

    expect(recaptchaSettings()->secret())->toBeNull()
        ->and(recaptchaSettings()->effective()['secret'])->toBe(['set' => false, 'source' => 'admin']);
});

test('a saved secret without a saved site key is not used', function () {
    storeSetting('recaptcha_secret_key_encrypted', Crypt::encryptString(RECAPTCHA_ADMIN_SECRET));

    expect(recaptchaSettings()->secret())->toBe(RECAPTCHA_ENV_SECRET)
        ->and(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ENV_KEY);
});

test('a secret that cannot be decrypted reads as not set, warns once, and the page asks for it again', function () {
    Log::spy();
    storeSetting('recaptcha_site_key', RECAPTCHA_ADMIN_KEY);
    storeSetting('recaptcha_secret_key_encrypted', 'not-a-valid-payload');
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));

    $this->get(route('google_recaptcha.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('recaptcha.secret_set', true)->where('recaptcha.secret_unreadable', true)
            ->where('effective.secret', ['set' => false, 'source' => 'admin']));
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => ! str_contains($message, 'not-a-valid-payload') && $context === []);

    // Keeping an unreadable secret is not allowed: it has to be entered again.
    $this->post(route('google_recaptcha.update'), recaptchaForm(['recaptcha_secret_key' => '']))
        ->assertSessionHasErrors('recaptcha_secret_key');
});

test('an unreadable settings table (during migrations, a fresh install) reads as nothing saved', function () {
    storeSetting('recaptcha_site_key', RECAPTCHA_ADMIN_KEY);
    recaptchaSettings()->forget();
    $connection = DB::getDefaultConnection();
    config(['database.connections.recaptcha_missing' => ['driver' => 'sqlite', 'database' => storage_path('framework/testing/missing/none.sqlite'), 'prefix' => '']]);
    DB::setDefaultConnection('recaptcha_missing');

    try {
        expect(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ENV_KEY)
            ->and(recaptchaSettings()->secret())->toBe(RECAPTCHA_ENV_SECRET);
    } finally {
        DB::setDefaultConnection($connection);
    }
});

// cache and the config

test('saving forgets the cached rows and business settings', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    Cache::put(StoreRecaptchaSettings::CACHE_KEY, ['site_key' => null, 'secret' => null]);
    Cache::put('business_settings', collect(['stale']));

    saveRecaptcha();

    expect(Cache::has('business_settings'))->toBeFalse()
        ->and(recaptchaSettings()->siteKey())->toBe(RECAPTCHA_ADMIN_KEY);
});

test('nothing is written into the config, so config:cache can never hold the secret', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    expect(config('services.recaptcha.site_key'))->toBe(RECAPTCHA_ENV_KEY)
        ->and(config('services.recaptcha.secret_key'))->toBe(RECAPTCHA_ENV_SECRET);
});

// the Recaptcha rule and the widget

test('the Recaptcha rule verifies the token with the effective secret', function () {
    Http::fake([Recaptcha::VERIFY_URL => Http::response(['success' => true])]);

    expect(validator(['g-recaptcha-response' => 'tok-env'], ['g-recaptcha-response' => [new Recaptcha]])->passes())->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['secret'] === RECAPTCHA_ENV_SECRET && $request['response'] === 'tok-env');

    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();

    expect(validator(['g-recaptcha-response' => 'tok-admin'], ['g-recaptcha-response' => [new Recaptcha]])->passes())->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['secret'] === RECAPTCHA_ADMIN_SECRET && $request['response'] === 'tok-admin');
});

test('the Recaptcha rule fails closed when a saved site key has no readable secret, never using CAPTCHA_SECRET', function () {
    storeSetting('recaptcha_site_key', RECAPTCHA_ADMIN_KEY);
    Http::fake();
    Log::spy();

    $validator = validator(['g-recaptcha-response' => 'tok'], ['g-recaptcha-response' => [new Recaptcha]]);

    expect($validator->fails())->toBeTrue();
    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context = []) => str_contains($message, 'CAPTCHA_SECRET') && $context === []);
});

test('the storefront widgets render the effective site key, escaped', function () {
    storeSetting('google_recaptcha', 1);

    $this->get('/temp/register')->assertOk()->assertSee('data-sitekey="'.RECAPTCHA_ENV_KEY.'"', false);

    storeSetting('recaptcha_site_key', RECAPTCHA_ADMIN_KEY);
    Cache::forget('business_settings');
    Cache::forget(StoreRecaptchaSettings::CACHE_KEY);

    $this->get('/temp/register')->assertOk()->assertSee('data-sitekey="'.RECAPTCHA_ADMIN_KEY.'"', false)->assertDontSee(RECAPTCHA_ENV_KEY);
});

test('a site key with markup in it is never printed raw', function () {
    config(['services.recaptcha.site_key' => '"><script>alert(1)</script>']);
    storeSetting('google_recaptcha', 1);

    $this->get('/temp/register')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('data-sitekey="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false);
});

// the public business settings API

test('the public business settings endpoint never lists the secret, and the site key is public', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));
    saveRecaptcha();
    storeSetting('site_name', 'Albaraka');

    $response = $this->postJson('/api/v1/store/business-settings', ['keys' => 'site_name,'.implode(',', StoreRecaptchaSettings::KEYS)])->assertOk();

    expect(array_column($response->json(), 'type'))->toEqualCanonicalizing(['site_name', 'recaptcha_site_key'])
        ->and($response->getContent())->not->toContain(Crypt::decryptString(recaptchaRows()['recaptcha_secret_key_encrypted']))
        ->not->toContain(recaptchaRows()['recaptcha_secret_key_encrypted']);
});

// the page

test('the page highlights only a field it holds', function () {
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));

    foreach (['recaptcha_site_key', 'recaptcha_secret_key', 'google_recaptcha'] as $key) {
        $this->get(route('google_recaptcha.index', ['highlight' => $key]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('highlight', $key));
    }

    foreach (['mail_host', ['recaptcha_site_key']] as $key) {
        $this->get(route('google_recaptcha.index', ['highlight' => $key]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('highlight', null));
    }
});

// authorization

test('saving refuses callers without the reCAPTCHA permission and writes nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('google_recaptcha.update'), recaptchaForm()), $caller);
    expect(recaptchaRows())->toBe([]);
})->with('refused store admin callers');

test('another settings permission never grants the page or its write', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings', 'analytics_tools_configuration', 'features_activation'));

    $this->get(route('google_recaptcha.index'))->assertForbidden();
    $this->post(route('google_recaptcha.update'), recaptchaForm())->assertForbidden();
    expect(recaptchaRows())->toBe([]);
});

test('WMS authentication alone never grants the page or its write', function () {
    actingAsAdmin();

    $this->get(route('google_recaptcha.index'))->assertRedirectToRoute('shop.login');
    $this->post(route('google_recaptcha.update'), recaptchaForm())->assertRedirectToRoute('shop.login');
    expect(recaptchaRows())->toBe([]);
});

// validation

test('the form refuses unsafe or invalid values before any write', function (array $overrides, string $field) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('google_recaptcha_configuration'));

    $this->postJson(route('google_recaptcha.update'), recaptchaForm($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(recaptchaRows())->toBe([]);
})->with([
    'a quote in the site key' => [['recaptcha_site_key' => '6LeAdminKeyAbCdEfGhIjKlMnOp"QrStUvWx'], 'recaptcha_site_key'],
    'angle brackets in the site key' => [['recaptcha_site_key' => '6LeAdminKeyAbCdEfGh<script>alert(1)'], 'recaptcha_site_key'],
    'a newline in the site key' => [['recaptcha_site_key' => "6LeAdminKeyAbCdEfGhIjKlMnOpQr\nStUvWx"], 'recaptcha_site_key'],
    'a trailing newline in the site key' => [['recaptcha_site_key' => RECAPTCHA_ADMIN_KEY."\n"], 'recaptcha_site_key'],
    'a space in the site key' => [['recaptcha_site_key' => '6LeAdminKey AbCdEfGhIjKlMnOpQrStUvWx'], 'recaptcha_site_key'],
    'a too short site key' => [['recaptcha_site_key' => 'short'], 'recaptcha_site_key'],
    'a too long site key' => [['recaptcha_site_key' => str_repeat('a', 101)], 'recaptcha_site_key'],
    'a line break in the secret' => [['recaptcha_secret_key' => "secret\nvalue"], 'recaptcha_secret_key'],
    'a trailing line break in the secret' => [['recaptcha_secret_key' => "secret-value\n"], 'recaptcha_secret_key'],
    'a too long secret' => [['recaptcha_secret_key' => str_repeat('x', 256)], 'recaptcha_secret_key'],
    'a secret without a site key' => [['recaptcha_site_key' => ''], 'recaptcha_site_key'],
    'a switch that is not boolean' => [['settings' => ['google_recaptcha' => 'maybe']], 'settings.google_recaptcha'],
    'another page\'s switch' => [['settings' => ['google_analytics' => 1]], 'settings'],
    'an unknown field' => [['CAPTCHA_SECRET' => 'x'], 'settings'],
    'a mail key' => [['mail_host' => 'x'], 'settings'],
]);
