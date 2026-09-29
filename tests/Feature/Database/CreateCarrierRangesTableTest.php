<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('carrier_ranges has the expected columns', function () {
    expect(Schema::hasTable('carrier_ranges'))->toBeTrue();
    expect(Schema::hasColumns('carrier_ranges', [
        'id', 'carrier_id', 'billing_type', 'delimiter1', 'delimiter2', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('carrier_ranges')->insert([
        'carrier_id' => 1,
        'billing_type' => 'weight',
        'delimiter1' => 0,
        'delimiter2' => 10,
    ]);

    $this->assertDatabaseHas('carrier_ranges', [
        'carrier_id' => 1,
        'billing_type' => 'weight',
    ]);
});
