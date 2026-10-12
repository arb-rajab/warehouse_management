<?php

use Illuminate\Support\Facades\Route;

test('currency and order permissions do not permit arbitrary legacy settings writes', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $this->post('/temp/admin/business-settings/update', ['types' => ['header_script'], 'header_script' => 'Unexpected'])->assertForbidden();
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
})->with(['currency_setup', 'order_configuration']);
test('legacy settings permissions cannot write reserved migrated keys including localized forms', function (string $key, bool $localized) {
    actingAsStoreUser(createStoreStaff('general_settings'));
    $this->post('/temp/admin/business-settings/update', ['types' => ['site_name', $localized ? ['en' => $key] : $key], 'site_name' => 'Must not change', $key => 'Unexpected'])->assertForbidden();
    $this->assertDatabaseMissing('business_settings', ['type' => $key]);
    $this->assertDatabaseMissing('business_settings', ['type' => 'site_name']);
})->with(['shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin', 'minimum_order_amount_check', 'minimum_order_amount', 'default_specific_product', 'default_representative_check', 'default_representative_id', 'system_default_currency', 'symbol_format', 'decimal_separator', 'no_of_decimals'])->with([false, true]);
test('unused create show and missing-method routes stay retired', function () {
    foreach (['cities.create', 'tax.create', 'countries.create', 'countries.store', 'countries.edit', 'countries.update', 'countries.destroy', 'countries.show', 'states.create', 'states.show', 'cities.show', 'zones.show', 'pick_up_points.show', 'tax.show', 'currency.update'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});
