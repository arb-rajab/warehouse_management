<?php

use App\Enums\ProductFieldSource;
use App\Models\User;
use App\Services\ProductFieldSourceService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Setup > Product data sources (.ai/rules/product-field-sources.md): one
 * owner per Otajer-synced product field, behind `product_field_sources`.
 */
beforeEach(function () {
    // Noise: an unrelated setting that no write here may touch.
    DB::table('business_settings')->insert(['type' => 'site_name', 'value' => 'Al Baraka']);
});

/** @return list<array<string, mixed>> */
function productFieldSourceSettingsSnapshot(): array
{
    return DB::table('business_settings')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

/** Every field products:sync writes, in registry order. */
const PRODUCT_SYNCED_FIELDS = [
    'name', 'ar_name', 'published', 'collection_id',
    'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
];

/** @return array<string, string> */
function productFieldSourcesPayload(array $overrides = []): array
{
    return array_replace(array_fill_keys(PRODUCT_SYNCED_FIELDS, 'otajer'), $overrides);
}

test('the page renders every synced field with its default owner when nothing is stored', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));

    $this->get(route('product_field_sources.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Setup/ProductFieldSources/Index')
        ->has('fields', 12)
        ->where('fields', array_map(fn (string $key): array => ['key' => $key, 'source' => 'otajer', 'default' => 'otajer'], PRODUCT_SYNCED_FIELDS)));
});

test('the page shows the stored owner of each field beside its default', function () {
    actingAsStoreUser(createStoreAdmin());
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'published' => 'admin', 'unit_price' => 'admin']);

    $this->get(route('product_field_sources.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('fields', array_map(
            fn (string $key): array => ['key' => $key, 'source' => in_array($key, ['name', 'published', 'unit_price'], true) ? 'admin' : 'otajer', 'default' => 'otajer'],
            PRODUCT_SYNCED_FIELDS,
        )));
});

test('saving stores exactly one owner per field and returns to the page', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));

    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => 'admin', 'description' => 'admin'])])
        ->assertRedirect(route('product_field_sources.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('success', 'Product data sources have been updated successfully');

    $expected = array_map(fn (string $field): string => in_array($field, ['name', 'description'], true) ? 'admin' : 'otajer', array_combine(PRODUCT_SYNCED_FIELDS, PRODUCT_SYNCED_FIELDS));
    $stored = array_combine(array_map(fn (string $field): string => 'product_source_'.$field, PRODUCT_SYNCED_FIELDS), array_values($expected));
    ksort($stored);

    expect(array_map(fn (ProductFieldSource $source): string => $source->value, app(ProductFieldSourceService::class)->sources()))->toBe($expected)
        ->and(DB::table('business_settings')->where('type', 'like', 'product_source_%')->orderBy('type')->pluck('value', 'type')->all())->toBe($stored)
        ->and(DB::table('business_settings')->where('type', 'site_name')->value('value'))->toBe('Al Baraka');
});

test('switching a field back replaces its choice, so both owners never apply at once', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));

    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['ar_name' => 'admin'])]);
    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['ar_name' => 'otajer'])]);

    expect(DB::table('business_settings')->where('type', 'product_source_ar_name')->pluck('value')->all())->toBe(['otajer'])
        ->and(app(ProductFieldSourceService::class)->isOtajerOwned('ar_name'))->toBeTrue()
        ->and(app(ProductFieldSourceService::class)->isAdminOwned('ar_name'))->toBeFalse();
});

test('a field can carry only a single value, not both options', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));
    $before = productFieldSourceSettingsSnapshot();

    $this->postJson(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => ['otajer', 'admin']])])
        ->assertUnprocessable()->assertJsonValidationErrors('sources.name');

    expect(productFieldSourceSettingsSnapshot())->toBe($before);
});

test('the request refuses unknown fields, unknown values and missing fields before any write', function (array $sources, string $error) {
    actingAsStoreUser(createStoreStaff('product_field_sources'));
    app(ProductFieldSourceService::class)->update(['published' => 'admin']);
    $before = productFieldSourceSettingsSnapshot();

    $this->postJson(route('product_field_sources.update'), ['sources' => $sources])
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(productFieldSourceSettingsSnapshot())->toBe($before);
})->with([
    'a field the sync does not write' => [productFieldSourcesPayload(['mat_id' => 'admin']), 'sources'],
    'an unknown owner' => [productFieldSourcesPayload(['name' => 'both']), 'sources.name'],
    'an empty owner' => [productFieldSourcesPayload(['ar_name' => '']), 'sources.ar_name'],
    'a missing field' => [['name' => 'admin', 'ar_name' => 'admin'], 'sources.published'],
    'a missing new field' => [array_diff_key(productFieldSourcesPayload(), ['unit_price' => 1]), 'sources.unit_price'],
]);

test('a missing or empty sources array is refused field by field, a non-array as a whole', function (mixed $sources, array $errors) {
    actingAsStoreUser(createStoreStaff('product_field_sources'));
    $before = productFieldSourceSettingsSnapshot();

    $this->postJson(route('product_field_sources.update'), $sources === null ? [] : ['sources' => $sources])
        ->assertUnprocessable()->assertJsonValidationErrors($errors);

    expect(productFieldSourceSettingsSnapshot())->toBe($before);
})->with([
    'missing' => [null, ['sources.name', 'sources.ar_name', 'sources.published']],
    'an empty array' => [[], ['sources.name', 'sources.ar_name', 'sources.published']],
    'a string' => ['admin', ['sources']],
]);

test('every caller without the permission is refused the page', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get(route('product_field_sources.index')), $caller);
})->with('refused store admin callers');

test('every caller without the permission is refused the save, and nothing changes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    $before = productFieldSourceSettingsSnapshot();

    assertStoreCallerRefused($this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => 'admin'])]), $caller);

    expect(productFieldSourceSettingsSnapshot())->toBe($before)
        ->and(app(ProductFieldSourceService::class)->isOtajerOwned('name'))->toBeTrue();
})->with('refused store admin callers');

test('a neighbouring settings permission does not open the page or the save', function () {
    actingAsStoreUser(createStoreStaff('general_settings', 'product_edit'));
    $before = productFieldSourceSettingsSnapshot();

    $this->get(route('product_field_sources.index'))->assertForbidden();
    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => 'admin'])])->assertForbidden();

    expect(productFieldSourceSettingsSnapshot())->toBe($before);
});

test('a warehouse admin session is not a store session', function () {
    $this->actingAs(User::factory()->create());
    $before = productFieldSourceSettingsSnapshot();

    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => 'admin'])])->assertRedirect(route('shop.login'));

    expect(productFieldSourceSettingsSnapshot())->toBe($before);
});
