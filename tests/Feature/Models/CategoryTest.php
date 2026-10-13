<?php

use App\Models\Attribute;
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

test('category hierarchy and filtering attribute relationships exclude unrelated data', function () {
    $root = Category::forceCreate(['name' => 'Root']);
    $child = Category::forceCreate(['name' => 'Child', 'parent_id' => $root->id, 'level' => 1]);
    $grandchild = Category::forceCreate(['name' => 'Grandchild', 'parent_id' => $child->id, 'level' => 2]);
    $noise = Category::forceCreate(['name' => 'Unrelated']);
    Category::forceCreate(['name' => 'Other child', 'parent_id' => $noise->id]);
    $attribute = Attribute::forceCreate(['name' => 'Color']);
    $otherAttribute = Attribute::forceCreate(['name' => 'Size']);
    $child->attributes()->attach($attribute);
    $noise->attributes()->attach($otherAttribute);
    expect($root->categories->modelKeys())->toBe([$child->id])
        ->and($root->childrenCategories->modelKeys())->toBe([$child->id])
        ->and($root->childrenCategories->first()->categories->modelKeys())->toBe([$grandchild->id])
        ->and($child->parentCategory->is($root))->toBeTrue()
        ->and($child->attributes->modelKeys())->toBe([$attribute->id])
        ->and($root->parentCategory)->toBeNull();
});
