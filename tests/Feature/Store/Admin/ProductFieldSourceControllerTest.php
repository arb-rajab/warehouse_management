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

/** @return array<string, string> */
function productFieldSourcesPayload(array $overrides = []): array
{
    return array_replace(['name' => 'otajer', 'ar_name' => 'otajer', 'published' => 'otajer'], $overrides);
}

test('the page renders every synced field with its default owner when nothing is stored', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));

    $this->get(route('product_field_sources.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Setup/ProductFieldSources/Index')
        ->has('fields', 3)
        ->has('fields.0', fn (Assert $field) => $field->where('key', 'name')->where('source', 'otajer')->where('default', 'otajer'))
        ->has('fields.1', fn (Assert $field) => $field->where('key', 'ar_name')->where('source', 'otajer')->where('default', 'otajer'))
        ->has('fields.2', fn (Assert $field) => $field->where('key', 'published')->where('source', 'otajer')->where('default', 'otajer')));
});

test('the page shows the stored owner of each field beside its default', function () {
    actingAsStoreUser(createStoreAdmin());
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'published' => 'admin']);

    $this->get(route('product_field_sources.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('fields', [
            ['key' => 'name', 'source' => 'admin', 'default' => 'otajer'],
            ['key' => 'ar_name', 'source' => 'otajer', 'default' => 'otajer'],
            ['key' => 'published', 'source' => 'admin', 'default' => 'otajer'],
        ]));
});

test('saving stores exactly one owner per field and returns to the page', function () {
    actingAsStoreUser(createStoreStaff('product_field_sources'));

    $this->post(route('product_field_sources.update'), ['sources' => productFieldSourcesPayload(['name' => 'admin'])])
        ->assertRedirect(route('product_field_sources.index'))
        ->assertSessionHasNoErrors();

    expect(app(ProductFieldSourceService::class)->sources())->toBe([
        'name' => ProductFieldSource::Admin,
        'ar_name' => ProductFieldSource::Otajer,
        'published' => ProductFieldSource::Otajer,
    ])
        ->and(DB::table('business_settings')->where('type', 'like', 'product_source_%')->orderBy('type')->pluck('value', 'type')->all())->toBe([
            'product_source_ar_name' => 'otajer',
            'product_source_name' => 'admin',
            'product_source_published' => 'otajer',
        ])
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
    'a field the sync does not write' => [productFieldSourcesPayload(['unit_price' => 'admin']), 'sources'],
    'an unknown owner' => [productFieldSourcesPayload(['name' => 'both']), 'sources.name'],
    'an empty owner' => [productFieldSourcesPayload(['ar_name' => '']), 'sources.ar_name'],
    'a missing field' => [['name' => 'admin', 'ar_name' => 'admin'], 'sources.published'],
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
