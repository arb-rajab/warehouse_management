<?php

use App\Models\Product;
use App\Models\ProductSetting;
use Illuminate\Support\Facades\Schema;

test('the wms_product_settings table has a nullable minimum_pallets column', function () {
    expect(Schema::hasColumn('wms_product_settings', 'minimum_pallets'))->toBeTrue();

    $product = Product::factory()->unconfigured()->create();
    $setting = ProductSetting::query()->create(['product_id' => $product->id]);

    expect($setting->fresh()->minimum_pallets)->toBeNull();
});

test('minimum_pallets stores and returns a configured threshold, with noise from another product', function () {
    $product = Product::factory()->minimumPallets(10)->create();
    Product::factory()->minimumPallets(3)->create();

    expect($product->fresh()->minimum_pallets)->toBe(10);
});
