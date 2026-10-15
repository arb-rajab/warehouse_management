<?php

use Illuminate\Support\Facades\Route;

test('currency and order permissions do not permit arbitrary legacy settings writes', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $this->post(retiredSettingsWriterUri(), ['types' => ['header_script'], 'header_script' => 'Unexpected'])->assertNotFound();
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
})->with(['currency_setup', 'order_configuration']);
test('legacy settings permissions cannot write reserved migrated keys including localized forms', function (string $key, bool $localized) {
    actingAsStoreUser(createStoreStaff('website_appearance'));
    $this->post(retiredSettingsWriterUri(), ['types' => ['website_name', $localized ? ['en' => $key] : $key], 'website_name' => 'Must not change', $key => 'Unexpected'])->assertNotFound();
    $this->put(route('website.appearance.update'), $localized
        ? ['settings' => websiteAppearancePayload(['website_name' => 'Must not change']), 'types' => [['en' => $key]], $key => 'Unexpected']
        : ['settings' => websiteAppearancePayload(['website_name' => 'Must not change', $key => 'Unexpected'])])->assertSessionHasErrors($localized ? ['types', $key] : 'settings');
    $this->assertDatabaseMissing('business_settings', ['type' => $key]);
    $this->assertDatabaseMissing('business_settings', ['type' => 'website_name']);
})->with(['shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin', 'minimum_order_amount_check', 'minimum_order_amount', 'default_specific_product', 'default_representative_check', 'default_representative_id', 'system_default_currency', 'symbol_format', 'decimal_separator', 'no_of_decimals'])->with([false, true]);
test('unused create show and missing-method routes stay retired', function () {
    foreach (['cities.create', 'tax.create', 'countries.create', 'countries.store', 'countries.edit', 'countries.update', 'countries.destroy', 'countries.show', 'states.create', 'states.show', 'cities.show', 'zones.show', 'pick_up_points.show', 'tax.show', 'currency.update'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});
