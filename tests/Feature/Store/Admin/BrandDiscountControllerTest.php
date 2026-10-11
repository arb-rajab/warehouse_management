<?php

use App\Models\Brand;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the brand discount page can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    $this->get('/temp/admin/brands-wise-product-discount')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/BrandDiscounts/Index'));
});

test('brand discounts expose the full narrow translated brand shape and batched logos', function () {
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    $logo = Upload::factory()->create(['file_name' => null, 'external_link' => 'https://example.com/logo.png']);
    $brand = Brand::forceCreate(['name' => 'Dates', 'logo' => $logo->id]);
    Brand::forceCreate(['name' => 'Other', 'logo' => null]);
    DB::table('brand_translations')->insert(['brand_id' => $brand->id, 'lang' => 'en', 'name' => 'Translated dates']);
    $this->get('/temp/admin/brands-wise-product-discount')->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['per_page' => 20, 'search' => null])->has('brands.data', 2)
        ->has('brands.data.0', fn (Assert $row) => $row->where('id', $brand->id)->where('name', 'Translated dates')->where('logo_url', 'https://example.com/logo.png'))
        ->where('brands.data.1.logo_url', null));
});

test('brand discount listing pages searches and validates filters', function () {
    actingAsStoreUser(createStoreStaff('view_all_brands'));
    foreach (range(1, 21) as $number) {
        Brand::forceCreate(['name' => 'Dates '.$number]);
    }
    Brand::forceCreate(['name' => 'Other']);
    $this->get('/temp/admin/brands-wise-product-discount?search=Dates')->assertInertia(fn (Assert $page) => $page->has('brands.data', 20)->where('brands.meta.total', 21));
    $this->get('/temp/admin/brands-wise-product-discount?search=Dates&per_page=10&page=3')->assertInertia(fn (Assert $page) => $page->has('brands.data', 1)->where('brands.meta.total', 21));
    $this->get('/temp/admin/brands-wise-product-discount?per_page=999')->assertSessionHasErrors('per_page');
    $this->get('/temp/admin/brands-wise-product-discount?search='.str_repeat('x', 201))->assertSessionHasErrors('search');
});

test('brand discount read action refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/brands-wise-product-discount'), $caller);
})->with('refused store admin callers');

test('brand discounts update only the legacy eligible products and preserve period and prices', function () {
    $admin = createStoreAdmin();
    actingAsStoreUser(createStoreStaff('product_edit'));
    $brand = Brand::forceCreate(['name' => 'Dates']);
    $product = createStoreProduct(['brand_id' => $brand->id, 'user_id' => $admin->id, 'auction_product' => 0, 'discount' => 3, 'unit_price' => 12.5]);
    $seller = createStoreUser('seller');
    $sellerProduct = createStoreProduct(['brand_id' => $brand->id, 'user_id' => $seller->id, 'added_by' => 'seller', 'auction_product' => 0, 'discount' => 4]);
    $auction = createStoreProduct(['brand_id' => $brand->id, 'user_id' => $admin->id, 'auction_product' => 1, 'discount' => 5]);
    $other = createStoreProduct(['brand_id' => 999, 'user_id' => $admin->id, 'auction_product' => 0, 'discount' => 6]);
    $payload = ['brand_id' => $brand->id, 'discount' => 7.25, 'date_range' => '09-10-2026 12:00:00 to 11-10-2026 16:30:00', 'seller_product_discount' => 0];
    $this->post('/temp/admin/set-product-discount?search=Dates&per_page=10', $payload)->assertSessionHasNoErrors()->assertRedirect(route('brands_wise_product_discount', ['search' => 'Dates', 'per_page' => 10]));
    $product->refresh();
    expect((float) $product->getAttribute('discount'))->toBe(7.25)->and($product->getAttribute('discount_type'))->toBe('percent')
        ->and((int) $product->getAttribute('discount_start_date'))->toBe(strtotime('09-10-2026 12:00:00'))
        ->and((int) $product->getAttribute('discount_end_date'))->toBe(strtotime('11-10-2026 16:30:00'))
        ->and((float) $product->getAttribute('unit_price'))->toBe(12.5)
        ->and((float) $sellerProduct->fresh()->getAttribute('discount'))->toBe(4.0)
        ->and((float) $auction->fresh()->getAttribute('discount'))->toBe(5.0)
        ->and((float) $other->fresh()->getAttribute('discount'))->toBe(6.0);
    $this->post('/temp/admin/set-product-discount', [...$payload, 'discount' => 0, 'date_range' => ''])->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $product->fresh()->getAttribute('discount'))->toBe(0.0)->and($product->fresh()->getAttribute('discount_start_date'))->toBeNull()->and($product->fresh()->getAttribute('discount_end_date'))->toBeNull();
});

test('the explicit seller product flag preserves legacy inclusion semantics', function () {
    createStoreAdmin();
    actingAsStoreUser(createStoreStaff('product_edit'));
    $brand = Brand::forceCreate(['name' => 'Dates']);
    $seller = createStoreUser('seller');
    $product = createStoreProduct(['brand_id' => $brand->id, 'user_id' => $seller->id, 'added_by' => 'seller', 'auction_product' => 0]);
    $this->post('/temp/admin/set-product-discount', ['brand_id' => $brand->id, 'discount' => 101, 'seller_product_discount' => 1])->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $product->fresh()->getAttribute('discount'))->toBe(101.0);
});

test('invalid brand discount input cannot change prices or discounts', function (array $overrides, string $error) {
    $admin = createStoreAdmin();
    actingAsStoreUser(createStoreStaff('product_edit'));
    $brand = Brand::forceCreate(['name' => 'Dates']);
    $product = createStoreProduct(['brand_id' => $brand->id, 'user_id' => $admin->id, 'discount' => 3]);
    $this->post('/temp/admin/set-product-discount', ['brand_id' => $brand->id, 'discount' => 7, ...$overrides])->assertSessionHasErrors($error);
    expect((float) $product->fresh()->getAttribute('discount'))->toBe(3.0);
})->with([
    [['brand_id' => 999999], 'brand_id'], [['discount' => -1], 'discount'], [['discount' => 'invalid'], 'discount'],
    [['date_range' => 'garbage'], 'date_range'], [['date_range' => ['array']], 'date_range'], [['date_range' => '32-10-2026 12:00:00 to 01-11-2026 12:00:00'], 'date_range'],
    [['date_range' => '11-10-2026 12:00:00 to 09-10-2026 12:00:00'], 'date_range'], [['seller_product_discount' => 2], 'seller_product_discount'],
]);

test('brand discounts report the missing administrator prerequisite without writing', function () {
    actingAsStoreUser(createStoreStaff('product_edit'));
    $brand = Brand::forceCreate(['name' => 'Dates']);
    $product = createStoreProduct(['brand_id' => $brand->id, 'discount' => 3]);
    $this->post('/temp/admin/set-product-discount', ['brand_id' => $brand->id, 'discount' => 7])->assertSessionHasErrors('discount');
    expect((float) $product->fresh()->getAttribute('discount'))->toBe(3.0);
});

test('brand discount writes refuse unauthorized callers without changing products', function (string $caller) {
    $brand = Brand::forceCreate(['name' => 'Dates']);
    $product = createStoreProduct(['brand_id' => $brand->id, 'discount' => 3]);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/set-product-discount', ['brand_id' => $brand->id, 'discount' => 7]), $caller);
    expect((float) $product->fresh()->getAttribute('discount'))->toBe(3.0);
})->with('refused store admin callers');
