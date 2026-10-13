<?php

use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\App;

test('attribute relations exclude translations and values belonging to other attributes', function () {
    $attribute = Attribute::create(['name' => 'Size']);
    $other = Attribute::create(['name' => 'Material']);
    $translation = AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => 'en', 'name' => 'Dimensions']);
    AttributeTranslation::create(['attribute_id' => $other->id, 'lang' => 'en', 'name' => 'Fabric']);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Large']);
    AttributeValue::create(['attribute_id' => $other->id, 'value' => 'Cotton']);

    $loaded = Attribute::findOrFail($attribute->id);
    expect($loaded->relationLoaded('attribute_translations'))->toBeTrue()
        ->and($loaded->attribute_translations->modelKeys())->toBe([$translation->id])
        ->and($loaded->attribute_values()->pluck('id')->all())->toBe([$value->id]);
});

test('attribute names use an explicit language or the current locale and fall back to base content', function () {
    $attribute = Attribute::create(['name' => 'Size']);
    AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => 'en', 'name' => 'Dimensions']);
    AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => 'ar', 'name' => 'المقاس']);
    AttributeTranslation::create(['attribute_id' => Attribute::create(['name' => 'Noise'])->id, 'lang' => 'fr', 'name' => 'Unrelated']);
    App::setLocale('ar');
    $loaded = $attribute->fresh();

    expect($loaded->getTranslation('name'))->toBe('المقاس')
        ->and($loaded->getTranslation('name', 'en'))->toBe('Dimensions')
        ->and($loaded->getTranslation('name', 'fr'))->toBe('Size');
});

test('attributes without translations return their original name', function () {
    $attribute = Attribute::create(['name' => 'Material']);

    expect($attribute->getTranslation('name'))->toBe('Material')
        ->and($attribute->getTranslation('name', 'ar'))->toBe('Material');
});
