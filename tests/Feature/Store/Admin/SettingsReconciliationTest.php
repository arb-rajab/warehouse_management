<?php

use App\Http\Controllers\Store\Admin\SettingsController;
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
})->with(array_unique(array_merge(...array_column(SettingsController::PAGES, 'keys'))))->with([false, true]);

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
