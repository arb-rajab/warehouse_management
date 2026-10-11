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
