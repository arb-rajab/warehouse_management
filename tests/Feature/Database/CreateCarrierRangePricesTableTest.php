<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('carrier_range_prices has the expected columns', function () {
    expect(Schema::hasTable('carrier_range_prices'))->toBeTrue();
    expect(Schema::hasColumns('carrier_range_prices', [
        'id', 'carrier_id', 'carrier_range_id', 'zone_id', 'price', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('carrier_range_prices')->insert([
        'carrier_id' => 1,
        'carrier_range_id' => 1,
        'zone_id' => 1,
        'price' => 9.99,
    ]);

    $this->assertDatabaseHas('carrier_range_prices', [
        'carrier_id' => 1,
        'carrier_range_id' => 1,
        'zone_id' => 1,
        'price' => 9.99,
    ]);
});
