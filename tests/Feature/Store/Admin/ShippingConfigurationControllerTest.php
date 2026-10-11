<?php

use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('the shipping configuration screen can be rendered with narrow settings', function () {
    actingAsStoreUser(createStoreStaff('shipping_configuration'));
    $this->get('/temp/admin/shipping_configuration')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/ShippingConfiguration/Index')->where('settings', ['shipping_type' => null, 'flat_rate_shipping_cost' => 0, 'shipping_cost_admin' => 0]));
});
test('the shipping configuration screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/shipping_configuration'), $caller);
})->with('refused store admin callers');
test('shipping configuration preserves each shipping method without clearing WMS cache', function (string $method) {
    actingAsStoreUser(createStoreStaff('shipping_configuration'));
    Cache::put('wms-sentinel', 'retained');
    Cache::put('business_settings', collect());
    $this->post('/temp/admin/shipping_configuration/update', ['type' => 'shipping_type', 'shipping_type' => $method, 'header_script' => 'Unrelated'])->assertRedirect(route('shipping_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'shipping_type', 'value' => $method]);
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
    expect(Cache::get('wms-sentinel'))->toBe('retained')->and(Cache::has('business_settings'))->toBeFalse();
})->with(['product_wise_shipping', 'flat_rate', 'seller_wise_shipping', 'area_wise_shipping', 'carrier_wise_shipping']);
test('shipping costs save independently', function (string $key) {
    actingAsStoreUser(createStoreStaff('shipping_configuration'));
    $this->post('/temp/admin/shipping_configuration/update', ['type' => $key, $key => '12.34'])->assertRedirect(route('shipping_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => $key, 'value' => '12.34']);
})->with(['flat_rate_shipping_cost', 'shipping_cost_admin']);
test('shipping configuration validates keys, methods and costs', function (array $payload, string $error) {
    actingAsStoreUser(createStoreStaff('shipping_configuration'));
    $this->postJson('/temp/admin/shipping_configuration/update', $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('business_settings', 0);
})->with([[['type' => 'header_script', 'header_script' => 'Unrelated'], 'type'], [['type' => 'shipping_type', 'shipping_type' => 'invalid'], 'shipping_type'], [['type' => 'flat_rate_shipping_cost', 'flat_rate_shipping_cost' => -1], 'flat_rate_shipping_cost'], [['type' => 'shipping_cost_admin', 'shipping_cost_admin' => -1], 'shipping_cost_admin']]);
test('shipping configuration refuses writes without changing settings', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/shipping_configuration/update', ['type' => 'shipping_type', 'shipping_type' => 'flat_rate']), $caller);
    $this->assertDatabaseMissing('business_settings', ['type' => 'shipping_type']);
})->with('refused store admin callers');
test('WMS sessions cannot configure store shipping', function () {
    actingAsAdmin();
    $this->get('/temp/admin/shipping_configuration')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/shipping_configuration/update', ['type' => 'shipping_type', 'shipping_type' => 'flat_rate'])->assertRedirect(route('shop.login'));
    $this->assertDatabaseMissing('business_settings', ['type' => 'shipping_type']);
});
