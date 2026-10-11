<?php

use App\Models\Category;
use Illuminate\Support\Facades\DB;

test('a category eagerly loads only its own translated content', function () {
    $category = Category::forceCreate(['name' => 'Food']);
    $other = Category::forceCreate(['name' => 'Other']);
    DB::table('category_translations')->insert([
        ['category_id' => $category->id, 'lang' => 'ar', 'name' => 'طعام'],
        ['category_id' => $other->id, 'lang' => 'ar', 'name' => 'آخر'],
    ]);
    expect($category->fresh()->category_translations->pluck('name')->all())->toBe(['طعام'])
        ->and($category->fresh()->getTranslation('name', 'ar'))->toBe('طعام')
        ->and($category->fresh()->getTranslation('name', 'en'))->toBe('Food');
});
