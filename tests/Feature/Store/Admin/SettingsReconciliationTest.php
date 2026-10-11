<?php

use App\Http\Controllers\Store\Admin\SettingsController;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest;
use Illuminate\Support\Facades\DB;

// Both plain and localized input must be rejected before the first permitted write.
test('the legacy settings writer rejects every migrated settings key atomically', function (string $key, bool $localized) {
    actingAsStoreUser(createStoreStaff('general_settings'));
    DB::table('business_settings')->insert(['type' => 'site_name', 'value' => 'Original']);
    $this->post(route('business_settings.update'), [
        'types' => ['site_name', $localized ? ['ar' => $key] : $key],
        'site_name' => 'Changed',
        $key => '1',
    ])->assertForbidden();
    $this->assertDatabaseHas('business_settings', ['type' => 'site_name', 'value' => 'Original']);
    expect(DB::table('business_settings')->count())->toBe(1);
})->with(array_unique(array_merge(array_merge(...array_column(SettingsController::PAGES, 'keys')), UpdateWebsiteHeaderRequest::KEYS, UpdateWebsiteHomeRequest::KEYS, ['header_script', 'footer_script', 'google_map', 'google_map_latitude', 'google_map_longtitude', 'MAP_API_KEY'])))->with([false, true]);

test('the retained seller activation form has an exact two-key contract', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));
    foreach (['vendor_commission_activation', 'category_wise_commission'] as $key) {
        $this->post(route('business_settings.seller_commission.activation'), ['type' => $key, 'value' => 1])->assertOk()->assertContent('1');
        $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => '1']);
    }
    $this->postJson(route('business_settings.seller_commission.activation'), ['type' => 'system_default_currency', 'value' => 1])->assertUnprocessable();
    $this->postJson(route('business_settings.seller_commission.activation'), ['type' => 'category_wise_commission', 'value' => 0, 'settings' => ['site_name' => 'Injected']])->assertUnprocessable();
    $this->assertDatabaseHas('business_settings', ['type' => 'category_wise_commission', 'value' => '1']);
    expect(DB::table('business_settings')->count())->toBe(2);
});

test('seller activation compatibility does not grant staff access', function () {
    actingAsStoreUser(createStoreStaff('seller_commission_configuration', 'features_activation'));
    $this->post(route('business_settings.seller_commission.activation'), ['type' => 'category_wise_commission', 'value' => 1])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
});

test('the retained script form writes only its two existing settings', function () {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $payload = ['types' => ['header_script', 'footer_script'], 'header_script' => 'Existing script content', 'footer_script' => null];
    $this->post(route('website.scripts.compatibility.update'), $payload)->assertRedirect(route('website.appearance'));
    $this->assertDatabaseHas('business_settings', ['type' => 'header_script', 'value' => 'Existing script content']);
    $this->postJson(route('website.scripts.compatibility.update'), array_replace($payload, ['types' => [['ar' => 'header_script'], 'footer_script']]))->assertUnprocessable();
    $this->postJson(route('website.scripts.compatibility.update'), $payload + ['site_name' => 'Injected'])->assertUnprocessable();
    $this->post(route('website.scripts.compatibility.update'), array_replace($payload, ['header_script' => '<script>proposed()</script>']))->assertForbidden();
    $this->assertDatabaseHas('business_settings', ['type' => 'header_script', 'value' => 'Existing script content']);
    expect(DB::table('business_settings')->count())->toBe(2);
});

test('general settings permission cannot write scripts through either endpoint', function () {
    actingAsStoreUser(createStoreStaff('general_settings'));
    $this->post(route('website.scripts.compatibility.update'), ['types' => ['header_script', 'footer_script'], 'header_script' => 'script', 'footer_script' => null])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
});
