<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('business_settings')->insert([
        ['type' => 'google_map', 'value' => '1'],
        ['type' => 'google_map_longtitude', 'value' => '36.291'],
        ['type' => 'google_map_latitude', 'value' => '33.513'],
        ['type' => 'header_script', 'value' => 'existing script'],
    ]);
});

test('the Google Maps configuration retains its Blade picker until the map dependency is approved', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    config(['services.google_maps.key' => 'server-managed-key']);

    $this->get('/temp/admin/google-map')->assertOk()
        ->assertViewIs('shop.backend.setup_configurations.google_configuration.google_map')
        ->assertSee(route('google-map.update'), false)
        ->assertDontSee('name="MAP_API_KEY"', false)
        ->assertDontSee(retiredSettingsWriterUri(), false);
});

// The enabled screen's default-location form loads the Google Maps script
// through shop.frontend.partials.google_map; map activation is an owner
// decision, so this GET stays Blade.
test('the enabled Google Maps screen loads the Google Maps script and the disabled one does not', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));

    $this->get('/temp/admin/google-map')->assertOk()
        ->assertSee('https://maps.googleapis.com/maps/api/js?key=', false)
        ->assertSee('name="google_map_latitude"', false);

    DB::table('business_settings')->where('type', 'google_map')->update(['value' => '0']);
    $this->get('/temp/admin/google-map')->assertOk()
        ->assertDontSee('maps.googleapis.com', false)
        ->assertDontSee('name="google_map_latitude"', false);
});

test('the Google Maps configuration refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/google-map'), $caller);
})->with('refused store admin callers');

test('the Google Maps endpoint changes only allowlisted settings and invalidates their cache', function () {
    actingAsStoreUser(createStoreStaff('google_map_setting'));
    Cache::put('business_settings', 'old cache', 60);
    Cache::put('unrelated cache', 'preserved', 60);

    $this->post('/temp/admin/google-map', [
        'google_map' => '0',
        'google_map_longtitude' => 0,
        'google_map_latitude' => 0,
    ])->assertRedirect(route('google-map.index'));

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
