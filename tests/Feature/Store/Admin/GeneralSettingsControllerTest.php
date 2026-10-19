<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Models\BusinessSetting;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    DB::table('business_settings')->insert([
        ['type' => 'site_name', 'value' => 'Original store'],
        ['type' => 'header_logo', 'value' => '7'],
        ['type' => 'footer_logo', 'value' => '8'],
        ['type' => 'website_name', 'value' => 'Storefront'],
        ['type' => 'google_map', 'value' => '1'],
    ]);
});

/** @return list<array<string, mixed>> */
function generalSettingsSnapshot(): array
{
    return DB::table('business_settings')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

/** @return array<string, mixed> */
function generalSettingsPayload(array $overrides = []): array
{
    return array_replace([
        'site_name' => 'Al Baraka',
        'timezone' => config('app.timezone'),
        'system_logo_white' => null,
        'system_logo_black' => null,
        'admin_login_background' => null,
    ], $overrides);
}

test('general settings render the Inertia screen with exactly its own settings, every timezone and permitted previews', function () {
    $staff = actingAsStoreUser(createStoreStaff('general_settings'));
    $white = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'White logo', 'file_size' => 64, 'external_link' => 'https://example.com/white.png']);
    $black = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    DB::table('business_settings')->insert([
        ['type' => 'system_logo_white', 'value' => (string) $white->id],
        ['type' => 'system_logo_black', 'value' => (string) $black->id],
    ]);

    $this->get(route('general_setting.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Setup/GeneralSettings/Index')
        ->where('settings', [
            'site_name' => 'Original store',
            'system_logo_white' => (string) $white->id,
            'system_logo_black' => (string) $black->id,
            'admin_login_background' => '',
            'timezone' => 'UTC',
        ])
        ->where('timezones', DateTimeZone::listIdentifiers())
        ->where('initialFiles', [
            'system_logo_white' => [['id' => $white->id, 'name' => 'White logo', 'type' => 'image', 'size' => 64, 'url' => 'https://example.com/white.png']],
            'system_logo_black' => [],
            'admin_login_background' => [],
        ]));
});

test('general settings is an Inertia page: an Inertia visit is served, and both loads keep the strict policy', function () {
    actingAsStoreUser(createStoreStaff('general_settings'));

    foreach ([$this->get(route('general_setting.index')), $this->get(route('general_setting.index'), inertiaHeaders())->assertHeader('X-Inertia', 'true')] as $response) {
        $policy = $response->assertOk()->headers->get('Content-Security-Policy-Report-Only') ?? $response->headers->get('Content-Security-Policy');
        $scriptSrc = collect(explode(';', (string) $policy))->map(fn (string $directive) => trim($directive))->first(fn (string $directive) => str_starts_with($directive, 'script-src '));
        expect($scriptSrc)->toBe("script-src 'self'");
    }
});

test('the configured timezone stays selectable and savable even when PHP lists it only as an alias', function () {
    config(['app.timezone' => 'Etc/UTC']);
    actingAsStoreUser(createStoreStaff('general_settings'));

    $this->get(route('general_setting.index'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.timezone', 'Etc/UTC')
        ->where('timezones', fn ($timezones) => collect($timezones)->contains('Etc/UTC')));
    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['timezone' => 'Etc/UTC'])])
        ->assertRedirect(route('general_setting.index'));
    $this->assertDatabaseMissing('business_settings', ['type' => 'timezone']);
});

test('a general settings save writes only the four stored keys, as strings, and never the timezone or the environment', function () {
    $staff = actingAsStoreUser(createStoreStaff('general_settings'));
    [$white, $black, $background] = Upload::factory()->count(3)->create(['user_id' => $staff->id, 'type' => 'image']);
    Cache::put('business_settings', collect(), 60);
    Cache::put('unrelated cache', 'kept', 60);
    Log::spy();
    $untouched = DB::table('business_settings')->whereNotIn('type', ['site_name'])->orderBy('id')->get()->toArray();

    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload([
        'site_name' => 'Al Baraka Holland',
        'system_logo_white' => (string) $white->id,
        'system_logo_black' => (string) $black->id,
        'admin_login_background' => (string) $background->id,
    ])])->assertRedirect(route('general_setting.index'))->assertSessionMissing('env_write_refused');

    expect(get_setting('site_name'))->toBe('Al Baraka Holland')
        ->and(get_setting('system_logo_white'))->toBe((string) $white->id)
        ->and(get_setting('system_logo_black'))->toBe((string) $black->id)
        ->and(get_setting('admin_login_background'))->toBe((string) $background->id)
        ->and(DB::table('business_settings')->whereIn('type', ['timezone', 'APP_TIMEZONE', 'APP_NAME'])->exists())->toBeFalse()
        ->and(DB::table('business_settings')->whereNotIn('type', ['site_name', 'system_logo_white', 'system_logo_black', 'admin_login_background'])->orderBy('id')->get()->toArray())->toEqual($untouched)
        ->and(Cache::has('business_settings'))->toBeFalse()
        ->and(Cache::get('unrelated cache'))->toBe('kept');
    Log::shouldNotHaveReceived('warning');

    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['site_name' => ''])])
        ->assertRedirect(route('general_setting.index'));
    expect(get_setting('site_name'))->toBeNull()
        ->and(get_setting('system_logo_white'))->toBeNull()
        ->and(get_setting('system_logo_black'))->toBeNull()
        ->and(get_setting('admin_login_background'))->toBeNull()
        ->and(DB::table('business_settings')->where('type', 'site_name')->count())->toBe(1);
});

test('general settings writes refuse invalid, foreign, reserved and environment input before any change', function (Closure $payload, string $error) {
    $staff = actingAsStoreUser(createStoreStaff('general_settings'));
    $before = generalSettingsSnapshot();

    $this->postJson(route('general_setting.update'), $payload($staff))
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(generalSettingsSnapshot())->toEqual($before);
})->with([
    'no settings' => [fn () => [], 'settings'],
    'legacy types shape' => [fn () => ['types' => ['site_name'], 'site_name' => 'Injected'], 'settings'],
    'foreign top-level key' => [fn () => ['settings' => generalSettingsPayload(), 'header_logo' => '1'], 'header_logo'],
    'environment top-level key' => [fn () => ['settings' => generalSettingsPayload(), 'APP_TIMEZONE' => 'Asia/Damascus'], 'APP_TIMEZONE'],
    'foreign nested key' => [fn () => ['settings' => generalSettingsPayload(['footer_logo' => '1'])], 'settings'],
    'reserved map key' => [fn () => ['settings' => generalSettingsPayload(['google_map' => '0'])], 'settings'],
    'reserved script key' => [fn () => ['settings' => generalSettingsPayload(['header_script' => 'x'])], 'settings'],
    'environment nested key' => [fn () => ['settings' => generalSettingsPayload(['APP_NAME' => 'x'])], 'settings'],
    'force https key' => [fn () => ['settings' => generalSettingsPayload(['FORCE_HTTPS' => 'On'])], 'settings'],
    'missing field' => [fn () => ['settings' => array_diff_key(generalSettingsPayload(), ['system_logo_black' => true])], 'settings.system_logo_black'],
    'localized site name' => [fn () => ['settings' => generalSettingsPayload(['site_name' => ['ar' => 'Injected']])], 'settings.site_name'],
    'site name too long' => [fn () => ['settings' => generalSettingsPayload(['site_name' => str_repeat('a', 256)])], 'settings.site_name'],
    'missing timezone' => [fn () => ['settings' => generalSettingsPayload(['timezone' => null])], 'settings.timezone'],
    'unknown timezone' => [fn () => ['settings' => generalSettingsPayload(['timezone' => 'Mars/Olympus'])], 'settings.timezone'],
    'abbreviation timezone' => [fn () => ['settings' => generalSettingsPayload(['timezone' => 'EST'])], 'settings.timezone'],
    'different valid timezone' => [fn () => ['settings' => generalSettingsPayload(['timezone' => 'Asia/Damascus'])], 'settings.timezone'],
    'logo list' => [fn ($staff) => ['settings' => generalSettingsPayload(['system_logo_white' => '1,2'])], 'settings.system_logo_white'],
    'missing logo' => [fn () => ['settings' => generalSettingsPayload(['system_logo_black' => '999999'])], 'settings.system_logo_black'],
    'document logo' => [fn ($staff) => ['settings' => generalSettingsPayload(['admin_login_background' => (string) Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document'])->id])], 'settings.admin_login_background'],
    'foreign logo' => [fn () => ['settings' => generalSettingsPayload(['system_logo_white' => (string) Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image'])->id])], 'settings.system_logo_white'],
]);

test('a different timezone is refused with the environment message and leaves every field unsaved', function () {
    actingAsStoreUser(createStoreStaff('general_settings'));
    $before = generalSettingsSnapshot();

    $this->from(route('general_setting.index'))
        ->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['site_name' => 'Draft', 'timezone' => 'Asia/Damascus'])])
        ->assertRedirect(route('general_setting.index'))
        ->assertSessionHasErrors(['settings.timezone' => __('messages.store_timezone_environment_managed')]);

    expect(generalSettingsSnapshot())->toEqual($before)
        ->and(config('app.timezone'))->toBe('UTC');
});

test('general settings logos accept library images, and a retained foreign logo round-trips without replacement or preview access', function () {
    $staff = actingAsStoreUser(createStoreStaff('general_settings'));
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $otherForeign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    DB::table('business_settings')->insert(['type' => 'system_logo_white', 'value' => (string) $foreign->id]);

    $this->get(route('general_setting.index'))->assertInertia(fn (Assert $page) => $page
        ->where('settings.system_logo_white', (string) $foreign->id)
        ->where('initialFiles.system_logo_white', []));
    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['system_logo_white' => (string) $foreign->id])])
        ->assertRedirect(route('general_setting.index'));
    expect(get_setting('system_logo_white'))->toBe((string) $foreign->id);

    $before = generalSettingsSnapshot();
    $this->postJson(route('general_setting.update'), ['settings' => generalSettingsPayload(['system_logo_white' => (string) $foreign->id, 'system_logo_black' => (string) $foreign->id])])
        ->assertUnprocessable()->assertJsonValidationErrors('settings.system_logo_black');
    $this->postJson(route('general_setting.update'), ['settings' => generalSettingsPayload(['system_logo_white' => (string) $otherForeign->id])])
        ->assertUnprocessable()->assertJsonValidationErrors('settings.system_logo_white');
    expect(generalSettingsSnapshot())->toEqual($before);

    actingAsStoreUser(createStoreStaff('general_settings', 'view_uploads'));
    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['system_logo_black' => (string) $otherForeign->id])])
        ->assertRedirect(route('general_setting.index'));
    expect(get_setting('system_logo_black'))->toBe((string) $otherForeign->id);
    $this->get(route('general_setting.index'))->assertInertia(fn (Assert $page) => $page
        ->where('initialFiles.system_logo_black.0.id', $otherForeign->id));
});

test('general settings refuse unauthorized callers on every action without changes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = generalSettingsSnapshot();

    assertStoreCallerRefused($this->get(route('general_setting.index')), $caller);
    assertStoreCallerRefused($this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['site_name' => 'Refused'])]), $caller);

    expect(generalSettingsSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('the general settings permission is required: neighbouring settings permissions are refused', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $before = generalSettingsSnapshot();

    $this->get(route('general_setting.index'))->assertForbidden();
    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['site_name' => 'Refused'])])->assertForbidden();

    expect(generalSettingsSnapshot())->toEqual($before);
})->with(['footer_setup', 'website_appearance', 'edit_website_page', 'google_map_setting']);

test('WMS authentication grants no general settings action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = generalSettingsSnapshot();

    $this->get(route('general_setting.index'))->assertRedirect(route('shop.login'));
    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload(['site_name' => 'WMS'])])->assertRedirect(route('shop.login'));

    expect(generalSettingsSnapshot())->toEqual($before);
});

test('saved logos and site name still reach the Blade shells, the login pages and the mobile settings API', function () {
    $staff = actingAsStoreUser(createStoreStaff('general_settings', 'show_seller_products'));
    $white = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'external_link' => 'https://example.com/system-white.png']);
    $black = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'external_link' => 'https://example.com/system-black.png']);
    DB::table('business_settings')->insert(['type' => 'vendor_system_activation', 'value' => '1']);

    $this->post(route('general_setting.update'), ['settings' => generalSettingsPayload([
        'site_name' => 'Holland Store',
        'system_logo_white' => (string) $white->id,
        'system_logo_black' => (string) $black->id,
    ])])->assertRedirect(route('general_setting.index'));

    $this->get(route('products.seller'))->assertOk()
        ->assertSee('https://example.com/system-white.png', false)
        ->assertSee('alt="Holland Store"', false)
        ->assertSee('&copy; Holland Store', false);
    $this->getJson('/temp/api/v2/business-settings')->assertOk()
        ->assertJsonFragment(['type' => 'site_name', 'value' => 'Holland Store']);

    auth('store')->logout();
    $this->get('/temp/seller/login')->assertOk()->assertViewIs('shop.frontend.seller_login')
        ->assertSee('https://example.com/system-black.png', false);
    // The delivery boy route needs its addon; its view reads the same setting.
    expect(view('shop.frontend.deliveryboy_login')->render())->toContain('https://example.com/system-black.png');
});

test('the general settings routes keep their name and URI and the Blade screen is gone', function () {
    expect(route('general_setting.index', [], false))->toBe('/temp/admin/general-setting')
        ->and(route('general_setting.update', [], false))->toBe('/temp/admin/general-setting')
        ->and(view()->exists('shop.backend.setup_configurations.general_settings'))->toBeFalse()
        ->and(method_exists(BusinessSettingsController::class, 'general_setting'))->toBeFalse()
        ->and(BusinessSetting::query()->where('type', 'timezone')->exists())->toBeFalse();
});
