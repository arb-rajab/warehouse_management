<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProduct;
use App\Models\State;

function translatedClassifiedFixture(string $name): CustomerProduct
{
    return CustomerProduct::forceCreate(['name' => $name, 'slug' => 'classified-'.uniqid()]);
}

test('classified translations are eager loaded scoped to the product and retain fallback semantics', function () {
    $product = translatedClassifiedFixture('Default name');
    $other = translatedClassifiedFixture('Other product');
    $product->customer_product_translations()->create(['lang' => 'ar', 'name' => 'المنتج']);
    $other->customer_product_translations()->create(['lang' => 'ar', 'name' => 'غير مرتبط']);
    $product = $product->fresh();
    expect($product->relationLoaded('customer_product_translations'))->toBeTrue()
        ->and($product->customer_product_translations)->toHaveCount(1)
        ->and($product->getTranslation('name', 'ar'))->toBe('المنتج')
        ->and($product->getTranslation('name', 'en'))->toBe('Default name');
    app()->setLocale('ar');
    expect($product->getTranslation('name'))->toBe('المنتج')
        ->and($product->getTranslation('name', null))->toBe('المنتج')
        ->and($product->getTranslation('name', ''))->toBe('المنتج');
});

test('classified owner brand and category relationships resolve only their target', function () {
    $owner = createStoreUser();
    $otherOwner = createStoreUser();
    $category = Category::forceCreate(['name' => 'Selected', 'slug' => 'selected']);
    $otherCategory = Category::forceCreate(['name' => 'Other', 'slug' => 'other']);
    $brand = Brand::forceCreate(['name' => 'Selected brand', 'slug' => 'selected-brand']);
    $otherBrand = Brand::forceCreate(['name' => 'Other brand', 'slug' => 'other-brand']);
    $product = CustomerProduct::forceCreate(['name' => 'Owned', 'user_id' => $owner->id, 'category_id' => $category->id, 'brand_id' => $brand->id]);
    expect($product->user()->first()->id)->toBe($owner->id)->not->toBe($otherOwner->id)
        ->and($product->category()->first()->id)->toBe($category->id)->not->toBe($otherCategory->id)
        ->and($product->brand()->first()->id)->toBe($brand->id)->not->toBe($otherBrand->id);
});

test('legacy classified location relations keep their original foreign key names', function () {
    $state = State::forceCreate(['name' => 'Selected', 'country_id' => 1]);
    $otherState = State::forceCreate(['name' => 'Other', 'country_id' => 1]);
    $city = City::forceCreate(['name' => 'Selected city', 'state_id' => $state->id]);
    $otherCity = City::forceCreate(['name' => 'Other city', 'state_id' => $otherState->id]);
    $product = translatedClassifiedFixture('Location');
    $product->setAttribute('state_id', $state->id);
    $product->setAttribute('city_id', $city->id);
    expect($product->state()->first()->id)->toBe($state->id)->not->toBe($otherState->id)
        ->and($product->city()->first()->id)->toBe($city->id)->not->toBe($otherCity->id);
});
