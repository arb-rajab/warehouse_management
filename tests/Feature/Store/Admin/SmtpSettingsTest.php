<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Mail\EmailManager;
use App\Providers\AppServiceProvider;
use App\Support\StoreMailSettings;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * The SMTP settings page edits the mail settings that used to live only in
 * `.env` (StoreMailSettings, .ai/rules/admin-env-overrides.md): saved values
 * win, `.env` is the fallback, the server group (host, port, encryption,
 * username, password) is saved and read as one, and the password is stored
 * encrypted and never sent back.
 */

const SMTP_SETTINGS_SECRET = 'S3cret-smtp-pass!';

beforeEach(function () {
    // The server's `.env` values in these tests.
    config([
        'mail.default' => 'array',
        'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.host' => 'env.example.com',
        'mail.mailers.smtp.port' => 2525,
        'mail.mailers.smtp.scheme' => null,
        'mail.mailers.smtp.username' => 'env-user',
        'mail.mailers.smtp.password' => 'env-password',
        'mail.from.address' => 'env@example.com',
        'mail.from.name' => 'Env Shop',
    ]);
});

/** @return array<string, mixed> */
function smtpForm(array $overrides = []): array
{
    return [
        'mailer' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'scheme' => '',
        'username' => 'shop@example.com',
        'password' => SMTP_SETTINGS_SECRET,
        'from_address' => 'shop@example.com',
        'from_name' => 'Albaraka Holland',
        // The signed-in staff member's own password (createStoreUser's default).
        'current_password' => 'correct-password',
        ...$overrides,
    ];
}

/** The saved `business_settings` rows of the SMTP page, by type. */
function smtpRows(): array
{
    return DB::table('business_settings')->whereIn('type', array_values(StoreMailSettings::KEYS))
        ->pluck('value', 'type')->all();
}

function saveSmtp(array $overrides = []): void
{
    test()->post(route('smtp_settings.update'), smtpForm($overrides))->assertSessionHasNoErrors()->assertRedirectToRoute('smtp_settings.index');
}

// saving

test('the SMTP page saves every field and flashes its success message', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));

    $this->post(route('smtp_settings.update'), smtpForm(['scheme' => 'smtps', 'port' => 465]))
        ->assertRedirectToRoute('smtp_settings.index')
        ->assertInertiaFlash('success', __('store_admin.smtp_settings_have_been_updated_successfully'));

    expect(Arr::except(smtpRows(), 'mail_password_encrypted'))->toEqual([
        'mail_from_address' => 'shop@example.com',
        'mail_from_name' => 'Albaraka Holland',
        'mail_host' => 'smtp.example.com',
        'mail_mailer' => 'smtp',
        'mail_port' => '465',
        'mail_scheme' => 'smtps',
        'mail_username' => 'shop@example.com',
    ])->and(DB::table('business_settings')->whereIn('type', array_values(StoreMailSettings::KEYS))->whereNotNull('lang')->exists())->toBeFalse();
});

test('the password is stored encrypted, never in plain text', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    $stored = smtpRows()['mail_password_encrypted'];

    expect($stored)->not->toContain(SMTP_SETTINGS_SECRET)
        ->and(Crypt::decryptString($stored))->toBe(SMTP_SETTINGS_SECRET)
        ->and(serialize(Cache::get(StoreMailSettings::CACHE_KEY)))->not->toContain(SMTP_SETTINGS_SECRET);
});

test('the password never reaches the page, its props, the flash or the errors', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings', 'send_newsletter'));
    saveSmtp();

    $this->get(route('smtp_settings.index'))->assertOk()
        ->assertDontSee(SMTP_SETTINGS_SECRET)->assertDontSee('env-password')->assertDontSee('env-user')
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Smtp')
            ->where('smtp.password_set', true)->where('smtp.password_unreadable', false)
            ->missing('smtp.password')
            ->where('effective.password', ['set' => true, 'source' => 'admin']));

    // A refused save does not echo the password back either.
    $this->post(route('smtp_settings.update'), smtpForm(['password' => SMTP_SETTINGS_SECRET, 'from_name' => str_repeat('x', 256)]))
        ->assertSessionHasErrors('from_name');
    expect(json_encode(session('errors')?->getBag('default')->toArray()))->not->toContain(SMTP_SETTINGS_SECRET)
        ->and(session()->getOldInput('password'))->toBeNull();
});

test('the page shows the saved values and where each effective value comes from', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp(['from_name' => '', 'mailer' => '']);

    $this->get(route('smtp_settings.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Smtp')
            ->where('smtp', [
                'mailer' => null,
                'host' => 'smtp.example.com',
                'port' => 587,
                'scheme' => null,
                'username' => 'shop@example.com',
                'from_address' => 'shop@example.com',
                'from_name' => null,
                'password_set' => true,
                'password_unreadable' => false,
                'environment_mailer' => 'array',
            ])
            ->where('effective', [
                'mailer' => ['value' => 'array', 'source' => 'env'],
                'host' => ['value' => 'smtp.example.com', 'source' => 'admin'],
                'port' => ['value' => '587', 'source' => 'admin'],
                'scheme' => ['value' => null, 'source' => 'admin'],
                'username' => ['set' => true, 'source' => 'admin'],
                'password' => ['set' => true, 'source' => 'admin'],
                'from_address' => ['value' => 'shop@example.com', 'source' => 'admin'],
                'from_name' => ['value' => 'Env Shop', 'source' => 'env'],
            ]));
});

test('the page passes on only a field highlight it holds', function (mixed $highlight, ?string $expected) {
    actingAsStoreUser(createStoreStaff('smtp_settings'));

    $this->get(route('smtp_settings.index', $highlight === null ? [] : ['highlight' => $highlight]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('highlight', $expected));
})->with([
    'mailer' => ['mail_mailer', 'mail_mailer'],
    'host' => ['mail_host', 'mail_host'],
    'credentials' => ['mail_credentials', 'mail_credentials'],
    'unknown' => ['cash_payment', null],
    'array' => [['mail_host'], null],
    'missing' => [null, null],
]);

// precedence

test('with nothing saved the server .env values are used, as before', function () {
    $mail = app(StoreMailSettings::class);
    $mail->apply();

    expect(config('mail.default'))->toBe('array')
        ->and(config('mail.mailers.smtp.host'))->toBe('env.example.com')
        ->and(config('mail.mailers.smtp.username'))->toBe('env-user')
        ->and(config('mail.mailers.smtp.password'))->toBe('env-password')
        ->and(config('mail.from.address'))->toBe('env@example.com')
        ->and(collect($mail->effective())->pluck('source')->unique()->all())->toBe(['env']);
});

test('saved values win over the server .env values at runtime', function () {
    config(['mail.mailers.smtp.url' => 'smtp://url-user:url-pass@url.example.com:25']);
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('shop@example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe(SMTP_SETTINGS_SECRET)
        // MAIL_URL would otherwise replace the saved host and login.
        ->and(config('mail.mailers.smtp.url'))->toBeNull()
        ->and(config('mail.from.address'))->toBe('shop@example.com')
        ->and(config('mail.from.name'))->toBe('Albaraka Holland');
});

test('the server group is never mixed: a saved host never gets the .env username or password', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp(['username' => '', 'password' => '']);

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.username'))->toBeNull()
        ->and(config('mail.mailers.smtp.password'))->toBeNull();
});

test('without a saved host the whole server group comes from .env, the mailer and sender from the admin', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    $this->post(route('smtp_settings.update'), ['mailer' => 'smtp', 'from_name' => 'Admin Shop', 'current_password' => 'correct-password'])->assertSessionHasNoErrors();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.from.name'))->toBe('Admin Shop')
        ->and(config('mail.from.address'))->toBe('env@example.com')
        ->and(config('mail.mailers.smtp.host'))->toBe('env.example.com')
        ->and(config('mail.mailers.smtp.username'))->toBe('env-user')
        ->and(config('mail.mailers.smtp.password'))->toBe('env-password');
});

test('an already built mailer is rebuilt with the saved settings', function () {
    config(['mail.default' => 'smtp']);
    $before = app('mail.manager')->mailer('smtp')->getSymfonyTransport()->getStream()->getHost();
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    expect($before)->toBe('env.example.com')
        ->and(app('mail.manager')->mailer('smtp')->getSymfonyTransport()->getStream()->getHost())->toBe('smtp.example.com');
});

test('a queued job applies the settings saved since the worker started', function () {
    app(StoreMailSettings::class)->apply();
    storeSetting('mail_host', 'worker.example.com');
    storeSetting('mail_port', '587');
    app(StoreMailSettings::class)->forget();

    event(new JobProcessing('database', Mockery::mock(Job::class)->shouldIgnoreMissing()));

    expect(config('mail.mailers.smtp.host'))->toBe('worker.example.com')
        ->and(config('mail.mailers.smtp.password'))->toBeNull();
});

test('the provider never applies the saved settings in the console, so config:cache cannot write them', function () {
    storeSetting('mail_host', 'console.example.com');
    storeSetting('mail_port', '587');
    storeSetting('mail_password_encrypted', Crypt::encryptString(SMTP_SETTINGS_SECRET));
    app(StoreMailSettings::class)->forget();

    (fn () => $this->applyStoreMailSettings())->call(new AppServiceProvider(app()));

    expect(app()->runningInConsole())->toBeTrue()
        ->and(config('mail.mailers.smtp.host'))->toBe('env.example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('env-password');
});

test('an unreadable settings table (during migrations, a fresh install) reads as nothing saved', function () {
    storeSetting('mail_host', 'smtp.example.com');
    app(StoreMailSettings::class)->forget();
    $connection = DB::getDefaultConnection();
    config(['database.connections.smtp_missing' => ['driver' => 'sqlite', 'database' => storage_path('framework/testing/missing/none.sqlite'), 'prefix' => '']]);
    DB::setDefaultConnection('smtp_missing');

    try {
        app(StoreMailSettings::class)->apply();
    } finally {
        DB::setDefaultConnection($connection);
    }

    expect(config('mail.mailers.smtp.host'))->toBe('env.example.com')
        ->and(Cache::has(StoreMailSettings::CACHE_KEY))->toBeFalse();
});

// keeping, changing and clearing

test('an empty password keeps the saved one', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();
    $saved = smtpRows()['mail_password_encrypted'];

    saveSmtp(['password' => '', 'from_name' => 'Renamed']);

    expect(smtpRows()['mail_password_encrypted'])->toBe($saved)
        ->and(config('mail.mailers.smtp.password'))->toBe(SMTP_SETTINGS_SECRET);
});

test('the saved password can be removed', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    saveSmtp(['password' => '', 'password_clear' => '1']);

    expect(smtpRows())->not->toHaveKey('mail_password_encrypted')
        ->and(config('mail.mailers.smtp.password'))->toBeNull();
});

test('a new password and removing the saved one at once is refused', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    $this->post(route('smtp_settings.update'), smtpForm(['password' => 'other', 'password_clear' => '1']))->assertSessionHasErrors('password');
});

test('the saved password is only kept for the host and username it was saved with', function (array $change) {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();
    $saved = smtpRows();

    $this->post(route('smtp_settings.update'), smtpForm(['password' => '', ...$change]))
        ->assertSessionHasErrors(['password' => __('store_admin.smtp_password_must_be_entered_again')]);
    expect(smtpRows())->toBe($saved);

    saveSmtp(['password' => 'new-password', ...$change]);
    expect(Crypt::decryptString(smtpRows()['mail_password_encrypted']))->toBe('new-password');
})->with([
    'another host' => [['host' => 'attacker.example.net']],
    'another username' => [['username' => 'someone@example.net']],
]);

test('emptying a field falls back to .env, and emptying the host removes the whole server group', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    saveSmtp();

    $this->post(route('smtp_settings.update'), ['password_clear' => '1', 'current_password' => 'correct-password'])->assertSessionHasNoErrors();

    expect(smtpRows())->toBe([])
        ->and(config('mail.default'))->toBe('array')
        ->and(config('mail.mailers.smtp.host'))->toBe('env.example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('env-password')
        ->and(config('mail.from.name'))->toBe('Env Shop');
});

test('saving forgets the business settings caches', function () {
    actingAsStoreUser(createStoreStaff('smtp_settings'));
    Cache::forever('business_settings', 'stale');
    app(StoreMailSettings::class)->stored();
    expect(Cache::has(StoreMailSettings::CACHE_KEY))->toBeTrue();

    saveSmtp();

    expect(Cache::has('business_settings'))->toBeFalse()
        ->and(app(StoreMailSettings::class)->stored()['host'])->toBe('smtp.example.com');
});

test('a saved password the application key cannot decrypt reads as not set and asks for it again', function () {
    storeSetting('mail_host', 'smtp.example.com');
    storeSetting('mail_port', '587');
    storeSetting('mail_password_encrypted', 'not-a-valid-payload');
    Log::spy();
    actingAsStoreUser(createStoreStaff('smtp_settings'));

    $this->get(route('smtp_settings.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('smtp.password_set', true)->where('smtp.password_unreadable', true)
            ->where('effective.password', ['set' => false, 'source' => 'admin']));
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => ! str_contains($message, 'not-a-valid-payload') && $context === []);
});

// authorization

test('saving refuses callers without the SMTP permission and writes nothing', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('smtp_settings.update'), smtpForm()), $caller);
    expect(smtpRows())->toBe([]);
})->with('refused store admin callers');

test('saving refuses staff holding other settings permissions only', function () {
    actingAsStoreUser(createStoreStaff('features_activation', 'send_newsletter', 'google_map_setting'));

    $this->post(route('smtp_settings.update'), smtpForm())->assertForbidden();
    $this->get(route('smtp_settings.index'))->assertForbidden();
    expect(smtpRows())->toBe([]);
});

test('WMS authentication alone never grants the SMTP page or its writes', function () {
    actingAsAdmin();

    $this->get(route('smtp_settings.index'))->assertRedirectToRoute('shop.login');
    $this->post(route('smtp_settings.update'), smtpForm())->assertRedirectToRoute('shop.login');
    expect(smtpRows())->toBe([]);
});

// validation

test('the SMTP form refuses unsafe or invalid values before any write', function (array $overrides, string $field) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('smtp_settings'));

    $this->postJson(route('smtp_settings.update'), smtpForm($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(smtpRows())->toBe([]);
})->with([
    'header injection in the from name' => [['from_name' => "Shop\r\nBcc: victim@example.com"], 'from_name'],
    'header injection in the from address' => [['from_address' => "shop@example.com\r\nBcc: victim@example.com"], 'from_address'],
    'an invalid from address' => [['from_address' => 'not-an-address'], 'from_address'],
    'a line break in the username' => [['username' => "user\nRCPT TO:<x@example.com>"], 'username'],
    'a trailing line break in the password' => [['password' => "pass\n"], 'password'],
    'a host with a scheme or path' => [['host' => 'smtp://evil.example.com/x'], 'host'],
    'a host with a space' => [['host' => 'smtp example.com'], 'host'],
    'a port out of range' => [['port' => 70000], 'port'],
    'a port that is not a number' => [['port' => 'abc'], 'port'],
    'a host without a port' => [['port' => ''], 'port'],
    'a username without a host' => [['host' => ''], 'host'],
    'an unknown mailer' => [['mailer' => 'sendmail'], 'mailer'],
    'an unknown encryption' => [['scheme' => 'tls'], 'scheme'],
    'a too long from name' => [['from_name' => str_repeat('x', 256)], 'from_name'],
    'an unknown field' => [['MAIL_PASSWORD' => 'x'], 'MAIL_PASSWORD'],
    'another settings key' => [['cash_payment' => '1'], 'cash_payment'],
]);

// test email

test('the test email is sent at once with the saved settings', function () {
    Mail::fake();
    actingAsStoreUser(createStoreStaff('smtp_settings', 'send_newsletter'));
    saveSmtp();

    $this->post(route('test.smtp'), ['email' => 'recipient@example.com'])->assertRedirectToRoute('smtp_settings.index')
        ->assertInertiaFlash('success', __('store_admin.test_email_has_been_sent_successfully'));

    Mail::assertNothingQueued();
    Mail::assertSent(EmailManager::class, fn (EmailManager $mail) => $mail->hasTo('recipient@example.com') && $mail->array['from'] === 'shop@example.com');
});

test('a test email the mail server refuses shows an error that names no setting value', function () {
    Mail::shouldReceive('to')->once()->andThrow(new TransportException('Failed to authenticate on SMTP server with username "secret-user"'));
    Log::spy();
    actingAsStoreUser(createStoreStaff('smtp_settings', 'send_newsletter'));

    $this->post(route('test.smtp'), ['email' => 'recipient@example.com'])
        ->assertSessionHasErrors(['email' => __('store_admin.test_email_could_not_be_sent')]);
    expect(json_encode(session('errors')->getBag('default')->toArray()))->not->toContain('secret-user');
    Log::shouldHaveReceived('warning')->once()->with('The SMTP test email could not be sent.', ['exception' => TransportException::class]);
});

// the public business settings API

test('the public business settings endpoint never lists the SMTP settings', function () {
    storeSetting('site_name', 'Albaraka');
    storeSetting('mail_host', 'smtp.example.com');
    storeSetting('mail_username', 'shop@example.com');
    storeSetting('mail_password_encrypted', Crypt::encryptString(SMTP_SETTINGS_SECRET));

    $response = $this->postJson('/api/v1/store/business-settings', ['keys' => 'site_name,'.implode(',', StoreMailSettings::KEYS)])->assertOk();

    expect(array_column($response->json(), 'type'))->toBe(['site_name'])
        ->and($response->getContent())->not->toContain('smtp.example.com')->not->toContain('shop@example.com');
});
