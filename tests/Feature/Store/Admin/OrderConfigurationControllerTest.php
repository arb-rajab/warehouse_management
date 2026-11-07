<?php

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the order configuration screen can be rendered with a narrow product and representative', function () {
    $product = Product::factory()->create(['id' => 123400, 'name' => 'Selected product']);
    Product::factory()->create(['id' => 123401, 'name' => 'Noise product']);
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1, 'name' => 'Representative'])->save();
    createStoreUser('customer');
    DB::table('business_settings')->insert(['type' => 'default_specific_product', 'value' => '123400']);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->get('/temp/admin/order-configuration')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/OrderConfiguration/Index')->where('settings', ['minimum_order_amount_check' => false, 'minimum_order_amount' => 0, 'default_specific_product' => '123400', 'default_representative_check' => false, 'default_representative_id' => null])->has('selectedProduct', fn (Assert $row) => $row->where('id', $product->id)->where('label', 'Selected product')->where('description', '#123400')->where('icon', 'package')->etc())->where('selectedRepresentative', null)->missing('representatives'));
});
test('the order configuration screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/order-configuration'), $caller);
})->with('refused store admin callers');
test('order amount and default product ID configuration save without changing unrelated settings or WMS cache', function () {
    Product::factory()->create(['id' => 123400]);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    Cache::put('wms-sentinel', 'retained');
    Cache::put('business_settings', collect());
    $this->post('/temp/admin/order-configuration', ['minimum_order_amount_check' => 0, 'minimum_order_amount' => '10.50', 'default_specific_product' => '123400', 'header_script' => 'Unrelated'])->assertRedirect(route('order_configuration.index'));
    $this->assertDatabaseHas('business_settings', ['type' => 'default_specific_product', 'value' => '123400']);
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_order_amount_check', 'value' => '0']);
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_order_amount', 'value' => '10.50']);
    $this->assertDatabaseMissing('business_settings', ['type' => 'header_script']);
    expect(Cache::get('wms-sentinel'))->toBe('retained')->and(Cache::has('business_settings'))->toBeFalse();
});
test('order configuration validates amount, boolean and existing product ID before writing', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->postJson('/temp/admin/order-configuration', ['minimum_order_amount_check' => 'invalid', 'minimum_order_amount' => -1, 'default_specific_product' => 'missing'])->assertUnprocessable()->assertJsonValidationErrors(['minimum_order_amount_check', 'minimum_order_amount', 'default_specific_product']);
    $this->assertDatabaseCount('business_settings', 0);
});
test('the default product must be an existing numeric product ID', function (string $value) {
    $product = Product::factory()->create(['id' => 123400]);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->postJson('/temp/admin/order-configuration', ['minimum_order_amount_check' => 1, 'minimum_order_amount' => 10, 'default_specific_product' => $value])->assertUnprocessable()->assertJsonValidationErrors(['default_specific_product']);
    $this->assertDatabaseCount('business_settings', 0);
    expect($product->fresh())->not->toBeNull();
})->with(['unknown id' => '999999', 'trailing text' => '123400abc', 'old material code' => 'MAT1234']);
test('order amount configuration refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/order-configuration', ['minimum_order_amount_check' => 1, 'minimum_order_amount' => 10, 'default_specific_product' => '123400']), $caller);
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
test('product search is bounded JSON with the search option shape and product ID filtering', function () {
    $product = Product::factory()->create(['id' => 300001, 'name' => '<img src=x onerror=alert(1)>', 'thumbnail_img' => null]);
    Product::factory()->create(['id' => 400001, 'name' => 'Noise']);
    actingAsStoreUser(createStoreStaff('order_configuration'));
    expect($this->getJson('/temp/admin/product/search?q=300001')->assertOk()->json('data'))->toEqual([['id' => $product->id, 'label' => '<img src=x onerror=alert(1)>', 'description' => '#300001', 'image' => null, 'icon' => 'package', 'badge' => null, 'extra' => ['mat_id' => '300001']]]);
    foreach (range(2, 23) as $number) {
        Product::factory()->create(['id' => 300000 + $number]);
    }
    $results = $this->getJson('/temp/admin/product/search?q=3000')->assertOk()->json('data');
    expect($results)->toHaveCount(20)->and(collect($results)->pluck('id'))->not->toContain(400001);
});
test('representative search offers only representatives with phone, email and discount, pages and refuses callers', function () {
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1, 'name' => 'Representative One', 'rep_discount_percentage' => 10, 'phone' => '0611111111', 'email' => 'rep1@example.test'])->save();
    createStoreUser('customer')->forceFill(['name' => 'Representative Customer'])->save();
    foreach (range(1, 21) as $number) {
        createStoreUser('customer')->forceFill(['is_rep' => 1, 'name' => sprintf('Rep Page %02d', $number)])->save();
    }
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $one = $this->getJson(route('order_configuration.representatives', ['q' => 'representative one']))->assertOk();
    expect($one->json('data'))->toHaveCount(1)->and($one->json('data.0'))->toEqual([
        'id' => $rep->id, 'label' => 'Representative One', 'description' => '0611111111 · rep1@example.test', 'image' => null, 'icon' => 'user',
        'badge' => ['text' => '10% discount', 'tone' => 'info'], 'extra' => ['rep_discount_percentage' => 10],
    ]);
    $all = $this->getJson(route('order_configuration.representatives'))->assertOk();
    expect($all->json('data'))->toHaveCount(20)->and($all->json('meta.total'))->toBe(22)
        ->and(collect($all->json('data'))->pluck('label')->all())->not->toContain('Representative Customer');
    $this->getJson(route('order_configuration.representatives', ['q' => str_repeat('x', 101)]))->assertUnprocessable()->assertJsonValidationErrors('q');
});
test('the configured default representative is sent as a search option', function () {
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1, 'name' => 'Default Rep'])->save();
    DB::table('business_settings')->insert(['type' => 'default_representative_id', 'value' => (string) $rep->id]);
    actingAsStoreUser(createStoreStaff('order_configuration'));

    $this->get('/temp/admin/order-configuration')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedRepresentative.id', $rep->id)->where('selectedRepresentative.label', 'Default Rep')->where('selectedRepresentative.icon', 'user'));
});
test('representative search refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('order_configuration.representatives')), $caller);
})->with('refused store admin callers');
test('product search validates query length', function (string $query) {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->getJson('/temp/admin/product/search?'.http_build_query(['q' => $query]))->assertUnprocessable()->assertJsonValidationErrors('q');
})->with(['', '300', str_repeat('x', 101)]);
test('product search refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/product/search?q=3000'), $caller);
})->with('refused store admin callers');
test('WMS identity cannot read or write order configuration or search', function () {
    actingAsAdmin();
    $this->get('/temp/admin/order-configuration')->assertRedirect(route('shop.login'));
    $this->get('/temp/admin/product/search?q=3000')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/order-configuration', [])->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/order-configuration/representative', [])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('business_settings', 0);
});
