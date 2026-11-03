<?php

use App\Enums\ProductFieldSource;
use App\Models\Product;
use App\Services\ProductFieldSourceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ProductFieldSourceService: the registry of Otajer-synced product fields,
 * their defaults and the stored owner of each (.ai/rules/product-field-sources.md).
 */
function fieldSources(): ProductFieldSourceService
{
    return app(ProductFieldSourceService::class);
}

/** Every field products:sync writes, in registry order. */
const SYNCED_FIELDS = [
    'name', 'ar_name', 'published', 'collection_id',
    'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
];

/**
 * Every synced field's owner, Otajer unless overridden.
 *
 * @param  array<string, ProductFieldSource>  $overrides
 * @return array<string, ProductFieldSource>
 */
function expectedSources(array $overrides = []): array
{
    return array_replace(array_fill_keys(SYNCED_FIELDS, ProductFieldSource::Otajer), $overrides);
}

/** @return array<string, string|null> */
function storedFieldSourceRows(): array
{
    return DB::table('business_settings')->where('type', 'like', 'product_source_%')->orderBy('id')
        ->get(['type', 'value'])->map(fn (object $row): array => [$row->type => $row->value])->collapse()->all();
}

test('the registry lists exactly the columns products:sync writes, each Otajer-owned by default', function () {
    expect(fieldSources()->fields())->toBe(SYNCED_FIELDS)
        ->and(array_map(fn (string $field) => fieldSources()->defaultFor($field), fieldSources()->fields()))
        ->toBe(array_values(expectedSources()))
        ->and(fieldSources()->insertValueFor('name'))->toBe('')
        ->and(fieldSources()->insertValueFor('ar_name'))->toBe('')
        ->and(fieldSources()->insertValueFor('published'))->toBe(0)
        ->and(fieldSources()->insertValueFor('collection_id'))->toBe(0)
        ->and(fieldSources()->insertValueFor('serial'))->toBe('')
        ->and(fieldSources()->insertValueFor('unit_price'))->toBe(0)
        ->and(fieldSources()->insertValueFor('unit'))->toBe('')
        ->and(fieldSources()->insertValueFor('api_unitId'))->toBe('')
        ->and(fieldSources()->insertValueFor('api_unit_name'))->toBe('')
        ->and(fieldSources()->insertValueFor('tax'))->toBe(0)
        ->and(fieldSources()->insertValueFor('description'))->toBe('')
        ->and(fieldSources()->insertValueFor('unit_equal'))->toBe(1);
});

test('every setting key fits the business_settings type column', function () {
    foreach (fieldSources()->fields() as $field) {
        expect(strlen(ProductFieldSourceService::settingKey($field)))->toBeLessThanOrEqual(30);
    }
});

test('with nothing stored every field reports its default owner, preserving the behaviour before the setting', function () {
    expect(fieldSources()->sources())->toBe(expectedSources())
        ->and(fieldSources()->isOtajerOwned('name'))->toBeTrue()
        ->and(fieldSources()->isOtajerOwned('unit_price'))->toBeTrue()
        ->and(fieldSources()->isAdminOwned('name'))->toBeFalse()
        ->and(fieldSources()->otajerOwnedFields())->toBe(SYNCED_FIELDS)
        ->and(fieldSources()->adminOwnedFields())->toBe([]);
});

test('a stored choice wins over the default, and an unreadable stored value falls back to the default', function () {
    DB::table('business_settings')->insert([
        ['type' => 'product_source_name', 'value' => 'admin', 'lang' => null],
        ['type' => 'product_source_ar_name', 'value' => 'both', 'lang' => null],
        // Noise: a localized row and an unrelated setting are ignored.
        ['type' => 'product_source_published', 'value' => 'admin', 'lang' => 'ar'],
        ['type' => 'site_name', 'value' => 'admin', 'lang' => null],
    ]);

    expect(fieldSources()->sourceFor('name'))->toBe(ProductFieldSource::Admin)
        ->and(fieldSources()->sourceFor('ar_name'))->toBe(ProductFieldSource::Otajer)
        ->and(fieldSources()->sourceFor('published'))->toBe(ProductFieldSource::Otajer)
        ->and(fieldSources()->adminOwnedFields())->toBe(['name'])
        ->and(fieldSources()->otajerOwnedFields())->toBe(array_values(array_diff(SYNCED_FIELDS, ['name'])));
});

test('the oldest of duplicate rows decides, so a field never reports two owners', function () {
    DB::table('business_settings')->insert([
        ['type' => 'product_source_name', 'value' => 'admin'],
        ['type' => 'product_source_name', 'value' => 'otajer'],
    ]);

    expect(fieldSources()->sourceFor('name'))->toBe(ProductFieldSource::Admin)
        ->and(fieldSources()->isOtajerOwned('name'))->toBeFalse();
});

test('an unregistered field is refused rather than guessed', function (string $method) {
    expect(fn () => fieldSources()->{$method}('mat_id'))->toThrow(InvalidArgumentException::class);
})->with(['sourceFor', 'isOtajerOwned', 'isAdminOwned', 'defaultFor', 'insertValueFor']);

test('update stores one row per field and switching a field replaces its single choice', function () {
    Cache::put('business_settings', 'stale');

    fieldSources()->update(['name' => ProductFieldSource::Admin, 'published' => 'admin']);

    expect(storedFieldSourceRows())->toBe(['product_source_name' => 'admin', 'product_source_published' => 'admin'])
        ->and(Cache::has('business_settings'))->toBeFalse();

    fieldSources()->update(['name' => 'otajer']);

    expect(storedFieldSourceRows())->toBe(['product_source_name' => 'otajer', 'product_source_published' => 'admin'])
        ->and(DB::table('business_settings')->where('type', 'product_source_name')->count())->toBe(1)
        ->and(fieldSources()->sources())->toBe(expectedSources(['published' => ProductFieldSource::Admin]));
});

test('update removes stray duplicate and localized rows for the field it writes', function () {
    DB::table('business_settings')->insert([
        ['type' => 'product_source_name', 'value' => 'otajer', 'lang' => null],
        ['type' => 'product_source_name', 'value' => 'admin', 'lang' => null],
        ['type' => 'product_source_name', 'value' => 'admin', 'lang' => 'ar'],
        ['type' => 'site_name', 'value' => 'Store', 'lang' => null],
    ]);

    fieldSources()->update(['name' => 'admin']);

    expect(DB::table('business_settings')->where('type', 'product_source_name')->get(['value', 'lang'])->map(fn (object $row) => (array) $row)->all())
        ->toBe([['value' => 'admin', 'lang' => null]])
        ->and(DB::table('business_settings')->where('type', 'site_name')->value('value'))->toBe('Store');
});

test('update checks every key and value before writing anything', function (array $choices) {
    fieldSources()->update(['ar_name' => 'admin']);
    $before = storedFieldSourceRows();

    expect(fn () => fieldSources()->update($choices))->toThrow(InvalidArgumentException::class)
        ->and(storedFieldSourceRows())->toBe($before);
})->with([
    'unknown field' => [['name' => 'admin', 'mat_id' => 'admin']],
    'unknown value' => [['name' => 'admin', 'published' => 'both']],
    'empty value' => [['name' => '']],
]);

test('only a feed-origin product has read-only fields, and only the Otajer-owned ones', function () {
    $feed = Product::factory()->feedOrigin()->create();
    $store = Product::factory()->create();

    expect(fieldSources()->readOnlyFieldsFor($feed))->toBe(SYNCED_FIELDS)
        ->and(fieldSources()->readOnlyFieldsFor($store->fresh()))->toBe([]);

    fieldSources()->update(['name' => 'admin', 'published' => 'admin', 'unit_price' => 'admin', 'description' => 'admin']);

    expect(fieldSources()->readOnlyFieldsFor($feed->fresh()))->toBe(array_values(array_diff(SYNCED_FIELDS, ['name', 'published', 'unit_price', 'description'])))
        ->and(fieldSources()->readOnlyFieldsFor($store->fresh()))->toBe([]);
});

test('an Otajer writer drops admin-owned fields on update and inserts their configured value on create', function () {
    // `category_id` is noise: the feed's category is not a synced field.
    $values = ['name' => 'Feed name', 'ar_name' => 'اسم', 'published' => 1, 'category_id' => 4];

    expect(fieldSources()->withoutAdminOwned($values))->toBe($values)
        ->and(fieldSources()->withInsertValues($values))->toBe($values);

    fieldSources()->update(['name' => 'admin', 'published' => 'admin']);

    expect(fieldSources()->withoutAdminOwned($values))->toBe(['ar_name' => 'اسم', 'category_id' => 4])
        ->and(fieldSources()->withInsertValues($values))->toBe(['name' => '', 'ar_name' => 'اسم', 'published' => 0, 'category_id' => 4])
        ->and(fieldSources()->withInsertValues(['category_id' => 4]))->toBe(['category_id' => 4]);

    fieldSources()->update(['unit_price' => 'admin', 'unit_equal' => 'admin', 'tax' => 'admin']);
    $priced = ['unit_price' => 12.5, 'tax' => 5, 'unit_equal' => 6, 'serial' => 'S1'];

    expect(fieldSources()->withoutAdminOwned($priced))->toBe(['serial' => 'S1'])
        ->and(fieldSources()->withInsertValues($priced))->toBe(['unit_price' => 0, 'tax' => 0, 'unit_equal' => 1, 'serial' => 'S1']);
});

test('an Otajer writer leaves the translation name of an admin-owned field alone', function () {
    $arabic = ['lang' => 'sa', 'name' => 'اسم', 'unit' => 'علبة'];

    expect(fieldSources()->translationValues('ar_name', $arabic, false))->toBe($arabic);

    fieldSources()->update(['ar_name' => 'admin']);

    expect(fieldSources()->translationValues('ar_name', $arabic, false))->toBe(['lang' => 'sa', 'unit' => 'علبة'])
        ->and(fieldSources()->translationValues('ar_name', $arabic, true))->toBe(['lang' => 'sa', 'name' => '', 'unit' => 'علبة'])
        ->and(fieldSources()->translationValues('name', ['lang' => 'en', 'name' => 'Name'], false))->toBe(['lang' => 'en', 'name' => 'Name']);
});

test('an Otajer writer leaves the translation description and English unit of an admin-owned field alone', function () {
    $english = ['lang' => 'en', 'name' => 'Name', 'unit' => 'Box', 'description' => 'Text'];
    $arabic = ['lang' => 'sa', 'name' => 'اسم', 'unit' => 'علبة', 'description' => 'نص'];

    expect(fieldSources()->translationValues('name', $english, false))->toBe($english);

    fieldSources()->update(['description' => 'admin', 'unit' => 'admin']);

    expect(fieldSources()->translationValues('name', $english, false))->toBe(['lang' => 'en', 'name' => 'Name'])
        ->and(fieldSources()->translationValues('name', $english, true))->toBe(['lang' => 'en', 'name' => 'Name', 'unit' => '', 'description' => ''])
        // The Arabic unit has no field of its own, so only the description is guarded there.
        ->and(fieldSources()->translationValues('ar_name', $arabic, false))->toBe(['lang' => 'sa', 'name' => 'اسم', 'unit' => 'علبة'])
        ->and(fieldSources()->translationValues('ar_name', $arabic, true))->toBe(['lang' => 'sa', 'name' => 'اسم', 'unit' => 'علبة', 'description' => '']);
});
