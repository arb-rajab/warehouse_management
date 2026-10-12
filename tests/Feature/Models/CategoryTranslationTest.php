<?php

use App\Models\Category;
use App\Models\CategoryTranslation;

test('a category translation belongs only to its category and resolves a deleted category to null', function () {
    $category = Category::forceCreate(['name' => 'Food']);
    $noise = Category::forceCreate(['name' => 'Other']);
    $translation = CategoryTranslation::create(['category_id' => $category->id, 'name' => 'طعام', 'lang' => 'ar']);
    CategoryTranslation::create(['category_id' => $noise->id, 'name' => 'آخر', 'lang' => 'ar']);
    expect($translation->category->is($category))->toBeTrue()->and($translation->category->is($noise))->toBeFalse();
    $category->delete();
    expect($translation->fresh()->category)->toBeNull();
});
