<?php

use Database\Seeders\StoreReferenceFilterSeeder;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedStoreCurrency();
});

test('reference fixtures fill every available country and state filter without changing existing geography', function () {
    $country = DB::table('countries')->insertGetId(['name' => 'Existing country', 'code' => 'XX', 'status' => 1]);
    $state = DB::table('states')->insertGetId(['name' => 'Existing state', 'country_id' => $country, 'status' => 1]);
    $anotherCountry = DB::table('countries')->insertGetId(['name' => 'Another country', 'code' => 'XY', 'status' => 1]);
    $before = (array) DB::table('states')->where('id', $state)->first();
    $this->seed(StoreReferenceFilterSeeder::class);
    actingAsStoreUser(createStoreStaff('manage_shipping_states', 'manage_shipping_cities'));
    foreach ([$country, $anotherCountry] as $id) {
        $states = $this->get(route('states.index', ['sort_country' => $id]))->assertOk()->inertiaProps('states.data');
        expect($states)->not->toBeEmpty();
        foreach ($states as $row) {
            expect($row['country_id'])->toBe($id);
        }
    }
    $cities = $this->get(route('cities.index', ['sort_state' => $state]))->assertOk()->inertiaProps('cities.data');
    expect($cities)->not->toBeEmpty();
    foreach ($cities as $row) {
        expect($row['state_id'])->toBe($state);
    }
    expect((array) DB::table('states')->where('id', $state)->first())->toBe($before);
});

test('reference fixtures satisfy review rating sorts and all upload types and sorts', function () {
    $this->seed(StoreReferenceFilterSeeder::class);
    actingAsStoreUser(createStoreStaff('view_product_reviews', 'view_uploads'));
    foreach (['asc' => [1, 5], 'desc' => [5, 1]] as $direction => $expected) {
        $reviews = $this->get(route('admin.reviews.index', ['rating' => 'rating,'.$direction]))->assertOk()->inertiaProps('reviews.data');
        expect(array_column($reviews, 'rating'))->toEqual($expected);
    }
    foreach (['image', 'video', 'audio', 'archive', 'document'] as $type) {
        foreach (['newest', 'oldest', 'smallest', 'largest'] as $sort) {
            $response = $this->getJson('/temp/admin/uploads/files?'.http_build_query(['type' => $type, 'sort' => $sort, 'search' => 'Filter coverage']))->assertOk();
            expect($response->json('data'))->toHaveCount(2);
            foreach ($response->json('data') as $row) {
                expect($row['type'])->toBe($type)->and($row['url'])->toBeNull();
            }
        }
    }
});

test('reference baselines support translation search and rerun without creating duplicates', function () {
    $this->seed(StoreReferenceFilterSeeder::class);
    $data = app(StoreFilterSeedData::class);
    actingAsStoreUser(createStoreStaff('language_setup'));
    foreach (['languages.show', 'app-translations.show'] as $route) {
        $rows = $this->get(route($route, [$data->find('languages', 'reference-baseline'), 'search' => 'store_filter_coverage']))->assertOk()->inertiaProps('translations.data');
        expect($rows)->toHaveCount(1)->and($rows[0]['key'])->toBe('store_filter_coverage_example');
    }
    $tables = ['countries', 'states', 'cities', 'zones', 'carriers', 'customer_packages', 'pages', 'languages', 'translations', 'app_translations', 'uploads', 'reviews', 'products', 'staff', 'notifications'];
    $counts = array_map(fn (string $table): int => DB::table($table)->count(), $tables);
    $this->seed(StoreReferenceFilterSeeder::class);
    expect(array_map(fn (string $table): int => DB::table($table)->count(), $tables))->toBe($counts);
    DB::table('reviews')->where('id', $data->find('reviews', 'reference-review-1'))->delete();
    $this->seed(StoreReferenceFilterSeeder::class);
    expect(DB::table('reviews')->where('product_id', $data->find('products', 'reference-reviewed-product'))->orderBy('rating')->pluck('rating')->all())->toBe([1, 5]);
});
