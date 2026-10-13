<?php

use App\Http\Controllers\Store\Admin\SettingsController;
use App\Mail\EmailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, array{string, string, string}> */
function writableSettingsPages(): array
{
    return [
        'activation' => ['activation', 'business_settings.update.activation', 'features_activation'],
        'chat' => ['facebookChat', 'facebook_chat.update', 'facebook_chat'],
        'comments' => ['facebookComments', 'facebook-comment.update', 'facebook_comment'],
        'analytics' => ['analytics', 'google_analytics.update', 'analytics_tools_configuration'],
        'pixel alias' => ['analytics', 'facebook_pixel.update', 'analytics_tools_configuration'],
        'recaptcha' => ['recaptcha', 'google_recaptcha.update', 'google_recaptcha_configuration'],
        'firebase' => ['firebase', 'google-firebase.update', 'google_firebase_setting'],
        'payments' => ['payments', 'payment_method.update', 'payment_methods_configurations'],
    ];
}

dataset('plain settings pages', array_map(fn (string $section, array $page): array => [$section, $page['index'], $page['permission']], array_keys(SettingsController::PAGES), array_values(SettingsController::PAGES)));
dataset('writable settings pages', writableSettingsPages());

test('each settings page can be rendered before any settings rows exist', function (string $section, string $route, string $permission) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Index'));
})->with('plain settings pages');

test('WMS authentication alone never grants settings page access', function (string $section, string $route, string $permission) {
    actingAsAdmin();
    $this->get(route($route))->assertRedirectToRoute('shop.login');
})->with('plain settings pages');

test('WMS authentication alone never grants settings writes', function (string $section, string $route, string $permission) {
    actingAsAdmin();
    $this->post(route($route), ['settings' => [SettingsController::PAGES[$section]['keys'][0] => 1]])->assertRedirectToRoute('shop.login');
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('writable settings pages');

test('each settings page renders with only its boolean settings and no environment credentials', function (string $section, string $route, string $permission) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    config(['mail.mailers.smtp.password' => 'smtp-secret', 'services.facebook.client_secret' => 'social-secret', 'filesystems.disks.s3.secret' => 'storage-secret', 'services.fcm.server_key' => 'firebase-secret']);
    DB::table('business_settings')->insert(['type' => 'unrelated_secret', 'value' => 'private-setting']);
    foreach (SettingsController::PAGES[$section]['keys'] as $key) {
        DB::table('business_settings')->insert(['type' => $key, 'value' => '1']);
    }
    $expected = array_fill_keys(SettingsController::PAGES[$section]['keys'], true);
    $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Settings/Index')->where('section', $section)->where('settings', $expected))
        ->assertDontSee('smtp-secret')->assertDontSee('social-secret')->assertDontSee('storage-secret')->assertDontSee('firebase-secret')->assertDontSee('private-setting');
})->with('plain settings pages');

test('each settings page refuses unauthorized callers', function (string $section, string $route, string $permission, string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route($route)), $caller);
})->with('plain settings pages')->with('refused store admin callers');

test('each settings endpoint accepts only its allowed keys, saves both boolean states, and redirects explicitly', function (string $section, string $route, string $permission) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    Artisan::spy();
    Cache::put('wms-sentinel', 'keep');
    DB::table('business_settings')->insert(['type' => 'noise', 'value' => 'unchanged']);
    foreach ([1, 0] as $value) {
        foreach (SettingsController::PAGES[$section]['keys'] as $key) {
            $this->post(route($route), ['settings' => [$key => $value]])->assertRedirectToRoute(SettingsController::PAGES[$section]['index']);
            $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => (string) $value]);
        }
    }
    $this->assertDatabaseHas('business_settings', ['type' => 'noise', 'value' => 'unchanged']);
    expect(Cache::get('wms-sentinel'))->toBe('keep');
    Artisan::shouldNotHaveReceived('call');
})->with('writable settings pages');

test('each settings endpoint rejects foreign keys atomically', function (string $section, string $route, string $permission, string $badKey) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    $key = SettingsController::PAGES[$section]['keys'][0];
    DB::table('business_settings')->insert(['type' => $key, 'value' => '0']);
    $this->postJson(route($route), ['settings' => [$key => 1, $badKey => 1]])->assertUnprocessable()->assertJsonValidationErrors('settings');
    $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => '0']);
    $this->assertDatabaseMissing('business_settings', ['type' => $badKey]);
})->with('writable settings pages')->with(['header_script', 'APP_KEY', 'maintenance_mode', 'FORCE_HTTPS', 'FILESYSTEM_DRIVER', 'seller_wholesale_product', 'proxypay']);

test('even valid migrated keys are refused on another settings page', function (string $section, string $route, string $permission) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    $keys = SettingsController::PAGES[$section]['keys'];
    $otherKey = $section === 'activation' ? 'facebook_chat' : 'coupon_system';
    $this->postJson(route($route), ['settings' => [$keys[0] => 1, $otherKey => 1]])->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('writable settings pages');

test('each settings write refuses unauthorized callers without changes', function (string $section, string $route, string $permission, string $caller) {
    actingAsRefusedStoreCaller($caller);
    $key = SettingsController::PAGES[$section]['keys'][0];
    DB::table('business_settings')->insert(['type' => $key, 'value' => '0']);
    assertStoreCallerRefused($this->post(route($route), ['settings' => [$key => 1]]), $caller);
    $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => '0']);
})->with('writable settings pages')->with('refused store admin callers');

test('a different settings permission never grants a migrated write', function (string $section, string $route, string $permission) {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post(route($route), ['settings' => [SettingsController::PAGES[$section]['keys'][0] => 1]])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('writable settings pages');

test('settings writes validate values, empty payloads, and old environment payloads', function (string $section, string $route, string $permission) {
    actingAsStoreUser($section === 'activation' ? createStoreAdmin(raizerAdmin: true) : createStoreStaff($permission));
    $key = SettingsController::PAGES[$section]['keys'][0];
    foreach ([['settings' => [$key => 'invalid']], ['settings' => []], ['types' => ['APP_KEY'], 'APP_KEY' => 'secret'], ['settings' => [$key => 1], 'MAIL_PASSWORD' => 'secret']] as $payload) {
        $this->postJson(route($route), $payload)->assertUnprocessable();
    }
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('writable settings pages');

test('the retired generic settings endpoint cannot bypass migrated permissions or overwrite their keys', function (string $key) {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post(retiredSettingsWriterUri(), ['types' => [$key], $key => '1'])->assertNotFound();
    $this->post(retiredSettingsWriterUri(), ['types' => [['ar' => $key]], $key => '1'])->assertNotFound();
    expect(DB::table('business_settings')->count())->toBe(0);
})->with(array_values(array_unique(array_merge(...array_column(SettingsController::PAGES, 'keys')))));

test('activation refuses a non-vendor super admin', function () {
    actingAsStoreUser(createStoreAdmin());
    $this->get(route('activation.index'))->assertForbidden();
    $this->post(route('business_settings.update.activation'), ['settings' => ['coupon_system' => 1]])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
});

test('SMTP testing requires both permissions and validates the recipient', function () {
    Mail::fake();
    actingAsStoreUser(createStoreStaff('smtp_settings', 'send_newsletter'));
    $this->postJson(route('test.smtp'), ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
    Mail::assertNothingQueued();
    $this->post(route('test.smtp'), ['email' => 'recipient@example.com'])->assertRedirectToRoute('smtp_settings.index');
    Mail::assertQueued(EmailManager::class, fn (EmailManager $mail) => $mail->hasTo('recipient@example.com'));
});

test('SMTP testing refuses unauthorized callers without sending', function (string $caller) {
    Mail::fake();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('test.smtp'), ['email' => 'recipient@example.com']), $caller);
    Mail::assertNothingQueued();
})->with('refused store admin callers');

test('SMTP testing refuses callers holding only one of its permissions', function (string $permission) {
    Mail::fake();
    actingAsStoreUser(createStoreStaff($permission));
    $this->post(route('test.smtp'), ['email' => 'recipient@example.com'])->assertForbidden();
    Mail::assertNothingQueued();
})->with(['smtp_settings', 'send_newsletter']);
