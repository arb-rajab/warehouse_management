<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Store\Admin\GoogleMapController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    DB::table('business_settings')->insert([
        ['type' => 'google_map', 'value' => '1'],
        ['type' => 'google_map_longtitude', 'value' => '36.291'],
        ['type' => 'google_map_latitude', 'value' => '33.513'],
        ['type' => 'header_script', 'value' => 'existing script'],
    ]);
});

test('the Google Maps settings screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));

    $this->get(route('google-map.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/GoogleMap/Index')
    );
});

test('the Google Maps page exposes exactly its stored settings', function (string $enabled) {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    DB::table('business_settings')->where('type', 'google_map')->update(['value' => $enabled]);
    Cache::forget('business_settings');

    $this->get(route('google-map.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/GoogleMap/Index')
            ->has('settings', fn (Assert $settings) => $settings
                ->where('google_map', $enabled === '1')
                ->where('google_map_latitude', '33.513')
                ->where('google_map_longtitude', '36.291')
                ->where('google_map_api_key_set', false)
                ->where('google_map_api_key_mask', '')
                ->where('google_map_api_key_from_env', false))
    );
})->with(['enabled' => '1', 'disabled' => '0']);

test('missing Google Maps settings render empty coordinates with the storefront map disabled', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    DB::table('business_settings')->whereIn('type', ['google_map', 'google_map_latitude', 'google_map_longtitude'])->delete();
    Cache::forget('business_settings');

    $this->get(route('google-map.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->has('settings', fn (Assert $settings) => $settings
                ->where('google_map', false)
                ->where('google_map_latitude', '')
                ->where('google_map_longtitude', '')
                ->where('google_map_api_key_set', false)
                ->where('google_map_api_key_mask', '')
                ->where('google_map_api_key_from_env', false))
    );
});

test('Google Maps routes retain their names URIs and permission on the admin controller', function () {
    foreach (['index' => 'GET', 'update' => 'POST'] as $action => $method) {
        $route = Route::getRoutes()->getByName('google-map.'.$action);
        expect($route->uri())->toBe('temp/admin/google-map')
            ->and($route->methods())->toContain($method)
            ->and($route->getActionName())->toBe(GoogleMapController::class.'@'.$action)
            ->and($route->gatherMiddleware())->toContain('permission:google_map_setting');
    }
    expect(method_exists(BusinessSettingsController::class, 'google_map'))->toBeFalse()
        ->and(file_exists(resource_path('views/shop/backend/setup_configurations/google_configuration/google_map.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/shop/frontend/partials/google_map.blade.php')))->toBeTrue();
});

test('the Google Maps configuration refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/google-map'), $caller);
})->with('refused store admin callers');

test('the Google Maps endpoint changes only allowlisted settings and invalidates their cache', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    Cache::put('business_settings', collect(), 60);
    Cache::put('unrelated cache', 'preserved', 60);

    $this->post('/temp/admin/google-map', [
        'google_map' => '0',
        'google_map_longtitude' => 0,
        'google_map_latitude' => 0,
    ])->assertRedirect(route('google-map.index'))
        ->assertInertiaFlash('success', 'Google map settings have been updated successfully');

    $this->assertDatabaseHas('business_settings', ['type' => 'google_map', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_longtitude', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_latitude', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'header_script', 'value' => 'existing script']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'MAP_API_KEY']);
    expect(Cache::has('business_settings'))->toBeFalse()
        ->and(Cache::get('unrelated cache'))->toBe('preserved');
});

test('saving the toggle preserves coordinates and saving coordinates preserves the toggle', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));

    $this->post('/temp/admin/google-map', ['google_map' => false])
        ->assertRedirect(route('google-map.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_longtitude', 'value' => '36.291']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_latitude', 'value' => '33.513']);

    $this->post('/temp/admin/google-map', [
        'google_map_longtitude' => -180,
        'google_map_latitude' => -90,
    ])->assertRedirect(route('google-map.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_longtitude', 'value' => '-180']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_latitude', 'value' => '-90']);
});

test('missing settings can be created without a prior business settings row', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    config(['services.google_maps.key' => 'ENVKEY-from-environment']);
    DB::table('business_settings')->where('type', 'google_map')->delete();

    $this->post('/temp/admin/google-map', ['google_map' => true])
        ->assertRedirect(route('google-map.index'));

    $this->assertDatabaseHas('business_settings', ['type' => 'google_map', 'value' => '1']);
});

test('invalid Google Maps updates preserve settings', function (array $payload, array $errors) {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();

    $this->postJson('/temp/admin/google-map', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($errors);

    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
})->with([
    'empty update' => [[], ['google_map']],
    'invalid toggle' => [['google_map' => 'enabled'], ['google_map']],
    'longitude below range' => [['google_map_longtitude' => -181, 'google_map_latitude' => 0], ['google_map_longtitude']],
    'latitude above range' => [['google_map_longtitude' => 0, 'google_map_latitude' => 91], ['google_map_latitude']],
    'longitude range' => [['google_map_longtitude' => 181, 'google_map_latitude' => 0], ['google_map_longtitude']],
    'latitude range' => [['google_map_longtitude' => 0, 'google_map_latitude' => -91], ['google_map_latitude']],
    'longitude missing' => [['google_map_latitude' => 33], ['google_map_longtitude']],
    'latitude missing' => [['google_map_longtitude' => 36], ['google_map_latitude']],
    'coordinates are numeric' => [['google_map_longtitude' => 'east', 'google_map_latitude' => 'north'], ['google_map_longtitude', 'google_map_latitude']],
]);

test('Google Maps writes refuse unauthorized callers without changing settings', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();

    assertStoreCallerRefused($this->post('/temp/admin/google-map', [
        'google_map' => false,
        'google_map_longtitude' => 0,
        'google_map_latitude' => 0,
    ]), $caller);

    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
})->with('refused store admin callers');

test('warehouse sessions cannot access or update store Google Maps settings', function () {
    actingAsAdmin();
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();

    $this->get('/temp/admin/google-map')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/google-map', ['google_map' => false])->assertRedirect(route('shop.login'));

    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('the retired generic settings endpoint cannot change migrated Google Maps settings', function (array $types) {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();

    $this->post(retiredSettingsWriterUri(), [
        'types' => ['header_script', ...$types],
        'header_script' => 'must not write before refusal',
        'google_map' => 0,
        'google_map_longtitude' => 0,
        'google_map_latitude' => 0,
    ])->assertNotFound();

    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
})->with([
    'toggle' => [['google_map']],
    'longitude' => [['google_map_longtitude']],
    'latitude' => [['google_map_latitude']],
    'translated key' => [[['ar' => 'google_map_latitude']]],
]);

test('map writes reject foreign plain and localized keys before writing allowed fields', function (bool $localized) {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();
    $payload = ['google_map' => false, 'types' => [$localized ? ['ar' => 'header_script'] : 'header_script'], 'header_script' => 'Injected'];
    $this->postJson(route('google-map.update'), $payload)->assertUnprocessable();
    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
})->with([false, true]);

test('map writes preserve localized rows and unrelated settings', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    DB::table('business_settings')->insert(['type' => 'google_map', 'value' => 'translated legacy value', 'lang' => 'ar']);
    $this->post(route('google-map.update'), ['google_map' => false])->assertRedirect(route('google-map.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map', 'lang' => 'ar', 'value' => 'translated legacy value']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map', 'lang' => null, 'value' => '0']);
});

test('Google Maps coordinates accept their positive boundaries', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    $this->post(route('google-map.update'), ['google_map_latitude' => 90, 'google_map_longtitude' => 180])
        ->assertRedirect(route('google-map.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_latitude', 'value' => '90']);
    $this->assertDatabaseHas('business_settings', ['type' => 'google_map_longtitude', 'value' => '180']);
});

// the Google Maps API key

function googleMapKeyRow(): ?string
{
    return DB::table('business_settings')->where('type', 'google_map_api_key')->whereNull('lang')->value('value');
}

beforeEach(function () {
    config(['services.google_maps.key' => null]);
});

test('staff save the API key as a business setting, with the success flash', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    Cache::put('business_settings', collect(), 60);

    $this->post(route('google-map.update'), ['google_map' => true, 'google_map_api_key' => '  AIzaSyTestKey_123-abc  '])
        ->assertRedirect(route('google-map.index'))
        ->assertInertiaFlash('success', 'Google map settings have been updated successfully');

    expect(googleMapKeyRow())->toBe('AIzaSyTestKey_123-abc')
        ->and(Cache::has('business_settings'))->toBeFalse();
});

test('the page never receives the saved key, only a mask and whether one is set', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    storeSetting('google_map_api_key', 'AIzaSyTestKey_123-abc');
    config(['services.google_maps.key' => 'ENVKEY-from-environment']);
    Cache::forget('business_settings');

    $response = $this->get(route('google-map.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('settings', fn (Assert $settings) => $settings
            ->where('google_map_api_key_set', true)
            ->where('google_map_api_key_mask', '••••••••-abc')
            ->where('google_map_api_key_from_env', true)
            ->etc())
    );

    expect($response->getContent())->not->toContain('AIzaSyTestKey_123-abc')->not->toContain('ENVKEY-from-environment');
});

test('a saved key shorter than twelve characters is masked with bullets only', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    storeSetting('google_map_api_key', 'short-key');
    Cache::forget('business_settings');

    $this->get(route('google-map.index'))->assertInertia(
        fn (Assert $page) => $page->where('settings.google_map_api_key_mask', '••••••••')
    );
});

test('a blank key keeps the saved key and a new key replaces it', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    storeSetting('google_map_api_key', 'AIzaSyOldKey');

    $this->post(route('google-map.update'), ['google_map' => true, 'google_map_api_key' => ''])
        ->assertRedirect(route('google-map.index'));
    expect(googleMapKeyRow())->toBe('AIzaSyOldKey');

    $this->post(route('google-map.update'), ['google_map' => true])->assertRedirect(route('google-map.index'));
    expect(googleMapKeyRow())->toBe('AIzaSyOldKey');

    $this->post(route('google-map.update'), ['google_map' => true, 'google_map_api_key' => 'AIzaSyNewKey'])
        ->assertRedirect(route('google-map.index'));
    expect(googleMapKeyRow())->toBe('AIzaSyNewKey')
        ->and(DB::table('business_settings')->where('type', 'google_map_api_key')->count())->toBe(1);
});

test('the key is required only while the map is on and neither a saved key nor MAP_API_KEY supplies one', function (bool $enabled, ?string $stored, ?string $env, bool $required) {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    if ($stored !== null) {
        storeSetting('google_map_api_key', $stored);
    }
    config(['services.google_maps.key' => $env]);
    Cache::forget('business_settings');

    $response = $this->postJson(route('google-map.update'), ['google_map' => $enabled]);

    if ($required) {
        $response->assertUnprocessable()->assertJsonValidationErrors(['google_map_api_key'])
            ->assertJsonPath('errors.google_map_api_key.0', 'The Google Maps API key field is required.');
    } else {
        $response->assertRedirect(route('google-map.index'));
    }
})->with([
    'on, nothing set' => [true, null, null, true],
    'on, saved key' => [true, 'AIzaSyOldKey', null, false],
    'on, env key' => [true, null, 'ENVKEY', false],
    'off, nothing set' => [false, null, null, false],
]);

test('an invalid key is refused and nothing is written', function (string $key) {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    $before = DB::table('business_settings')->orderBy('id')->get()->toArray();

    $this->postJson(route('google-map.update'), ['google_map' => true, 'google_map_api_key' => $key])
        ->assertUnprocessable()->assertJsonValidationErrors(['google_map_api_key']);

    expect(DB::table('business_settings')->orderBy('id')->get()->toArray())->toEqual($before);
})->with([
    'spaces inside' => ['AIza bad key'],
    'slash' => ['AIza/bad/key'],
    'too long' => [str_repeat('a', 256)],
]);

test('the key is refused for callers without the permission and is never written', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post('/temp/admin/google-map', ['google_map' => true, 'google_map_api_key' => 'AIzaSyTestKey']), $caller);

    expect(googleMapKeyRow())->toBeNull();
})->with('refused store admin callers');

test('the key is never logged', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    Log::spy();

    $this->post(route('google-map.update'), ['google_map' => true, 'google_map_api_key' => 'AIzaSyTestKey_secret'])
        ->assertRedirect(route('google-map.index'));

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
});

test('the public business settings API does not expose the saved key', function () {
    storeSetting('google_map_api_key', 'AIzaSyTestKey_secret');
    storeSetting('site_name', 'Al Baraka');

    $response = $this->getJson('/api/v1/store/business-settings')->assertOk();

    expect($response->getContent())->not->toContain('AIzaSyTestKey_secret');
});
