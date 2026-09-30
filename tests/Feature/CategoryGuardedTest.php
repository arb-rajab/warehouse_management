<?php

use App\Models\Category;

/**
 * Category declared `protected $guarded;` with no value: null, which
 * Eloquent's mass-assignment checks read as an empty list, so the model has
 * always been fully mass assignable. `[]` keeps exactly that behaviour; the
 * one difference is that mergeGuarded() no longer hits array_merge(null).
 */
test('every category attribute is mass assignable', function () {
    $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0, 'featured' => 1]);

    expect($category->fresh()->only(['name', 'slug', 'level', 'featured']))->toBe([
        'name' => 'Snacks',
        'slug' => 'snacks',
        'level' => 0,
        'featured' => 1,
    ]);
});

test('guarding more category attributes later works', function () {
    $category = (new Category)->mergeGuarded(['featured']);

    expect($category->isFillable('name'))->toBeTrue()
        ->and($category->isFillable('featured'))->toBeFalse();
});
