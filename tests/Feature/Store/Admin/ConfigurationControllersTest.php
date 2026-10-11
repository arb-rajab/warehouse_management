<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('shipping configuration renders and only accepts documented setting keys and values', function () {
    actingAsStoreUser(createStoreStaff('shipping_configuration'));
    DB::table('business_settings')->insert(['type' => 'shipping_type', 'value' => 'flat_rate']);

    $this->get('/temp/admin/shipping_configuration')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Shipping/Configuration/Index')->where('settings.shipping_type', 'flat_rate')
    );
    $this->post('/temp/admin/shipping_configuration/update', ['type' => 'unexpected_setting', 'unexpected_setting' => 'ignored'])->assertSessionHasErrors('type');
    $this->post('/temp/admin/shipping_configuration/update', ['type' => 'shipping_type', 'shipping_type' => 'area_wise_shipping'])->assertRedirect(route('shipping_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'shipping_type', 'value' => 'area_wise_shipping']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'unexpected_setting']);
});

test('order configuration renders, validates allowlisted writes and searches safely as JSON', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $representative = createStoreUser('customer');
    $representative->forceFill(['name' => 'Rep', 'is_rep' => true])->save();
    $product = createStoreProduct(['name' => '<img src=x onerror=alert(1)>', 'mat_id' => 'MAT-1000']);

    $this->get('/temp/admin/order-configuration')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/OrderConfiguration/Index')
            ->where('representatives.0.id', $representative->id)
            ->where('settings.minimum_order_amount_check', false)
    );
    $this->get('/temp/admin/product/search?query=MAT-')->assertOk()->assertJson([
        ['id' => $product->id, 'name' => '<img src=x onerror=alert(1)>', 'mat_id' => 'MAT-1000'],
    ])->assertHeader('content-type', 'application/json');
    $this->get('/temp/admin/product/search?query=x')->assertSessionHasErrors('query');

    $this->put('/temp/admin/order-configuration/minimum', [
        'minimum_order_amount_check' => true,
        'minimum_order_amount' => 25,
        'default_specific_product' => 'MAT-1000',
        'unexpected_setting' => 'ignored',
    ])->assertRedirect(route('order_configuration.index'));
    $this->put('/temp/admin/order-configuration/representative', [
        'default_representative_check' => true,
        'default_representative_id' => $representative->id,
    ])->assertRedirect(route('order_configuration.index'));

    $this->assertDatabaseHas('business_settings', ['type' => 'default_specific_product', 'value' => 'MAT-1000']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'unexpected_setting']);
});

test('configuration routes refuse callers without their permission and do not write', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/shipping_configuration'), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/shipping_configuration/update', ['type' => 'shipping_type', 'shipping_type' => 'flat_rate']), $caller);
    assertStoreCallerRefused($this->get('/temp/admin/order-configuration'), $caller);
    assertStoreCallerRefused($this->put('/temp/admin/order-configuration/minimum', ['minimum_order_amount_check' => true, 'minimum_order_amount' => 10, 'default_specific_product' => 'MAT-1']), $caller);
    assertStoreCallerRefused($this->put('/temp/admin/order-configuration/representative', ['default_representative_check' => false, 'default_representative_id' => 0]), $caller);
    assertStoreCallerRefused($this->get('/temp/admin/product/search?query=MAT-'), $caller);
    $this->assertDatabaseMissing('business_settings', ['type' => 'shipping_type']);
})->with('refused store admin callers');
