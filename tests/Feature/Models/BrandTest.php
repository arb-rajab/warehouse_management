<?php

use App\Models\Brand;
use Illuminate\Support\Facades\DB;

test('a brand eagerly loads only its own translated content', function () {
    $brand = Brand::forceCreate(['name' => 'Fresh']);
    $other = Brand::forceCreate(['name' => 'Other']);
    DB::table('brand_translations')->insert([
        ['brand_id' => $brand->id, 'lang' => 'ar', 'name' => 'طازج'],
        ['brand_id' => $other->id, 'lang' => 'ar', 'name' => 'آخر'],
    ]);
    expect($brand->fresh()->brand_translations->pluck('name')->all())->toBe(['طازج'])
        ->and($brand->fresh()->getTranslation('name', 'ar'))->toBe('طازج')
        ->and($brand->fresh()->getTranslation('name', 'en'))->toBe('Fresh');
});

test('categoryIdsFor lists, per brand, the categories of the products a shopper can see', function () {
    $acme = Brand::forceCreate(['name' => 'Acme'])->id;
    $zed = Brand::forceCreate(['name' => 'Zed'])->id;
    $none = Brand::forceCreate(['name' => 'None'])->id;
    createStoreProduct(['brand_id' => $acme, 'category_id' => 9, 'published' => 1, 'approved' => 1]);
    createStoreProduct(['brand_id' => $acme, 'category_id' => 4, 'published' => 1, 'approved' => 1]);
    createStoreProduct(['brand_id' => $acme, 'category_id' => 4, 'published' => 1, 'approved' => 1]);
    createStoreProduct(['brand_id' => $zed, 'category_id' => 5, 'published' => 1, 'approved' => 1]);
    createStoreProduct(['brand_id' => $zed, 'category_id' => 6, 'published' => 0, 'approved' => 1]);
    createStoreProduct(['brand_id' => $zed, 'category_id' => 7, 'published' => 1, 'approved' => 0]);
    createStoreProduct(['brand_id' => $zed, 'category_id' => 8, 'published' => 1, 'approved' => 1, 'digital' => 1]);
    createStoreProduct(['brand_id' => $zed, 'category_id' => null, 'published' => 1, 'approved' => 1]);
    createStoreProduct(['brand_id' => null, 'category_id' => 3, 'published' => 1, 'approved' => 1]);

    expect(Brand::categoryIdsFor([$acme, $zed, $none]))->toBe([$acme => [4, 9], $zed => [5], $none => []])
        ->and(Brand::categoryIdsFor([(string) $zed]))->toBe([$zed => [5]])
        ->and(Brand::categoryIdsFor([]))->toBe([]);
});
