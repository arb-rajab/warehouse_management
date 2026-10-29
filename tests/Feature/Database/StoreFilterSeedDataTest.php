<?php

use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Support\Facades\DB;

test('fixture ownership keys cannot be claimed twice', function () {
    $records = [
        ['table_name' => 'users', 'seed_key' => 'customer', 'record_id' => '1'],
        ['table_name' => 'users', 'seed_key' => 'customer', 'record_id' => '2'],
    ];
    DB::table('business_settings')->insert(['type' => StoreFilterSeedData::TRACKING_KEY, 'value' => json_encode(['version' => 1, 'records' => $records])]);
    expect(fn () => app(StoreFilterSeedData::class)->records())->toThrow(RuntimeException::class, 'Duplicate');
});

test('a fixture record cannot have two owners', function () {
    $records = [
        ['table_name' => 'users', 'seed_key' => 'customer', 'record_id' => '1'],
        ['table_name' => 'users', 'seed_key' => 'another', 'record_id' => '1'],
    ];
    DB::table('business_settings')->insert(['type' => StoreFilterSeedData::TRACKING_KEY, 'value' => json_encode(['version' => 1, 'records' => $records])]);
    expect(fn () => app(StoreFilterSeedData::class)->records())->toThrow(RuntimeException::class, 'Duplicate');
});

test('an existing reserved setting is never overwritten or adopted', function (?string $value) {
    DB::table('business_settings')->insert(['type' => StoreFilterSeedData::TRACKING_KEY, 'value' => $value]);
    $before = DB::table('business_settings')->first();
    expect(fn () => app(StoreFilterSeedData::class)->row('zones', 'example', ['name' => 'New fixture']))->toThrow(Exception::class);
    expect(DB::table('business_settings')->first())->toEqual($before)
        ->and(DB::table('zones')->count())->toBe(0);
})->with([null, 'unrelated existing setting', '{"version":2,"records":[]}']);

test('duplicate tracking settings refuse writes', function () {
    foreach (range(1, 2) as $index) {
        DB::table('business_settings')->insert(['type' => StoreFilterSeedData::TRACKING_KEY, 'value' => '{"version":1,"records":[]}']);
    }
    expect(fn () => app(StoreFilterSeedData::class)->row('zones', 'example', ['name' => 'New fixture']))->toThrow(RuntimeException::class, 'Duplicate');
    expect(DB::table('zones')->count())->toBe(0);
});
