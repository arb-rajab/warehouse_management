<?php

use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a product can only have one settings row', function () {
    $product = Product::factory()->create();

    expect(fn () => ProductSetting::factory()->create(['product_id' => $product->id]))
        ->toThrow(QueryException::class);
});

test('deleting a product cascades to its settings row and leaves other products alone', function () {
    // Pallets restrict a product delete, so this product deliberately has none.
    $product = Product::factory()->boxesCount(7)->create();
    $survivor = Product::factory()->boxesCount(9)->create();
    Pallet::factory()->create(['product_id' => $survivor->id]);

    $product->delete();

    expect(ProductSetting::query()->whereKey($product->id)->exists())->toBeFalse();
    expect(ProductSetting::query()->whereKey($survivor->id)->value('boxes_count'))->toBe(9);
});

test('boxes_count defaults to one when the row is inserted without it', function () {
    $product = Product::factory()->unconfigured()->create();

    $setting = ProductSetting::query()->create(['product_id' => $product->id]);

    expect($setting->fresh()->boxes_count)->toBe(1);
});

test('the table is wms-owned rather than a column on the shared products table', function () {
    expect(Schema::hasTable('wms_product_settings'))->toBeTrue();
    expect(Schema::hasColumn('products', 'boxes_count'))->toBeFalse();
});
