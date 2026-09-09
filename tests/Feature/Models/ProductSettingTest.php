<?php

use App\Models\Product;
use App\Models\ProductSetting;

test('a setting belongs to its product, and not to another one', function () {
    $product = Product::factory()->boxesCount(6)->create();
    Product::factory()->boxesCount(11)->create();

    $setting = ProductSetting::query()->whereKey($product->id)->firstOrFail();

    expect($setting->product->id)->toBe($product->id);
    expect($setting->boxes_count)->toBe(6);
});

test('the product id is the primary key, so there is no separate auto-incrementing id', function () {
    $product = Product::factory()->boxesCount(3)->create();
    $setting = ProductSetting::query()->whereKey($product->id)->firstOrFail();

    expect($setting->getKeyName())->toBe('product_id');
    expect($setting->getKey())->toBe($product->id);
    expect($setting->incrementing)->toBeFalse();
});

test('the model reads the wms-owned table', function () {
    expect((new ProductSetting)->getTable())->toBe('wms_product_settings');
});
