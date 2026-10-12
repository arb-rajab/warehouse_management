<?php

use App\Models\ProductsExport;
use Illuminate\Support\Facades\DB;

test('the product spreadsheet export eagerly loads stocks and preserves its existing rows and headings', function () {
    $first = createStoreProduct(['name' => 'Dates', 'description' => 'Fresh dates', 'added_by' => 'admin', 'user_id' => 42, 'category_id' => 7, 'brand_id' => 8, 'video_provider' => 'youtube', 'video_link' => 'https://example.com/video', 'unit_price' => 5.5, 'purchase_price' => 4.5, 'unit' => 'box', 'current_stock' => 3]);
    DB::table('product_stocks')->insert(['product_id' => $first->id, 'variant' => 'Large', 'price' => 8, 'qty' => 4]);
    $other = createStoreProduct(['name' => 'Oranges', 'current_stock' => 9]);
    $export = new ProductsExport;
    $products = $export->collection();
    expect($products->pluck('id')->all())->toBe([$first->id, $other->id])
        ->and($products[0]->relationLoaded('stocks'))->toBeTrue()
        ->and($products[1]->relationLoaded('stocks'))->toBeTrue()
        ->and(array_keys($products[0]->getAttributes()))->toBe(['id', 'name', 'description', 'added_by', 'user_id', 'category_id', 'brand_id', 'video_provider', 'video_link', 'unit_price', 'purchase_price', 'unit'])
        ->and($export->map($products[0]))->toEqual(['Dates', 'Fresh dates', 'admin', 42, 7, 8, 'youtube', 'https://example.com/video', 5.5, 4.5, 'box', 7])
        ->and($export->headings())->toBe(['name', 'description', 'added_by', 'user_id', 'category_id', 'brand_id', 'video_provider', 'video_link', 'unit_price', 'purchase_price', 'unit', 'current_stock', 'meta_title', 'meta_description']);
});
