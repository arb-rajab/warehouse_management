<?php

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the order configuration screen can be rendered with a narrow product and representative', function () {
    $product = Product::factory()->create(['name' => 'Selected product', 'mat_id' => 'MAT1234']);
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1, 'name' => 'Representative'])->save();
    createStoreUser('customer');
    DB::table('business_settings')->insert(['type' => 'default_specific_product', 'value' => 'MAT1234']);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->get('/temp/admin/order-configuration')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/OrderConfiguration/Index')->where('settings', ['minimum_order_amount_check' => false, 'minimum_order_amount' => 0, 'default_specific_product' => 'MAT1234', 'default_representative_check' => false, 'default_representative_id' => null])->has('selectedProduct', fn (Assert $row) => $row->where('id', $product->id)->where('name', 'Selected product')->where('mat_id', 'MAT1234'))->where('representatives', [['id' => $rep->id, 'name' => 'Representative']]));
});
test('the order configuration screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/order-configuration'), $caller);
})->with('refused store admin callers');
test('order amount and MAT ID configuration save without changing unrelated settings or WMS cache', function () {
    Product::factory()->create(['mat_id' => 'MAT1234']);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    Cache::put('wms-sentinel', 'retained');
    Cache::put('business_settings', collect());
    $this->post('/temp/admin/order-configuration', ['minimum_order_amount_check' => 0, 'minimum_order_amount' => '10.50', 'default_specific_product' => 'MAT1234', 'header_script' => 'Unrelated'])->assertRedirect(route('order_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'default_specific_product', 'value' => 'MAT1234']);
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_order_amount_check', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_order_amount', 'value' => '10.50']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
    expect(Cache::get('wms-sentinel'))->toBe('retained')->and(Cache::has('business_settings'))->toBeFalse();
});
test('order configuration validates amount, boolean and existing MAT ID before writing', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->postJson('/temp/admin/order-configuration', ['minimum_order_amount_check' => 'invalid', 'minimum_order_amount' => -1, 'default_specific_product' => 'missing'])->assertUnprocessable()->assertJsonValidationErrors(['minimum_order_amount_check', 'minimum_order_amount', 'default_specific_product']);
    $this->assertDatabaseCount('business_settings', 0);
});
test('order amount configuration refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/order-configuration', ['minimum_order_amount_check' => 1, 'minimum_order_amount' => 10, 'default_specific_product' => 'MAT1234']), $caller);
    $this->assertDatabaseMissing('business_settings', ['type' => 'minimum_order_amount']);
})->with('refused store admin callers');
test('default representative saves independently and excludes non-representatives', function () {
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1])->save();
    $noise = createStoreUser('customer');
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->post('/temp/admin/order-configuration/representative', ['default_representative_check' => 1, 'default_representative_id' => $rep->id])->assertRedirect(route('order_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'default_representative_id', 'value' => (string) $rep->id]);
    $this->postJson('/temp/admin/order-configuration/representative', ['default_representative_check' => 'invalid', 'default_representative_id' => $noise->id])->assertUnprocessable()->assertJsonValidationErrors(['default_representative_check', 'default_representative_id']);
    $this->assertDatabaseHas('business_settings', ['type' => 'default_representative_id', 'value' => (string) $rep->id]);
});
test('default representative configuration refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/order-configuration/representative', ['default_representative_check' => 1, 'default_representative_id' => 1]), $caller);
    $this->assertDatabaseMissing('business_settings', ['type' => 'default_representative_id']);
})->with('refused store admin callers');
test('product search is bounded JSON with the exact narrow shape and MAT ID filtering', function () {
    $product = Product::factory()->create(['mat_id' => 'MAT0001', 'name' => '<img src=x onerror=alert(1)>']);
    Product::factory()->create(['mat_id' => 'OTHER', 'name' => 'Noise']);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    expect($this->getJson('/temp/admin/product/search?query=MAT0001')->assertOk()->json())->toBe([['id' => $product->id, 'name' => '<img src=x onerror=alert(1)>', 'mat_id' => 'MAT0001']]);
    foreach (range(1, 22) as $number) {
        Product::factory()->create(['mat_id' => 'MAT0'.$number]);
    }
    $results = $this->getJson('/temp/admin/product/search?query=MAT0')->assertOk()->json();
    expect($results)->toHaveCount(20)->and(collect($results)->pluck('mat_id'))->not->toContain('OTHER');
});
test('product search validates query length', function (string $query) {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->getJson('/temp/admin/product/search?'.http_build_query(['query' => $query]))->assertUnprocessable()->assertJsonValidationErrors('query');
})->with(['', 'MAT', str_repeat('x', 256)]);
test('product search refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/product/search?query=MAT1'), $caller);
})->with('refused store admin callers');
test('WMS identity cannot read or write order configuration or search', function () {
    actingAsAdmin();
    $this->get('/temp/admin/order-configuration')->assertRedirect(route('shop.login'));
    $this->get('/temp/admin/product/search?query=MAT1')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/order-configuration', [])->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/order-configuration/representative', [])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('business_settings', 0);
});
