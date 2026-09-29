<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('carriers has the expected columns', function () {
    expect(Schema::hasTable('carriers'))->toBeTrue();
    expect(Schema::hasColumns('carriers', [
        'id', 'name', 'logo', 'transit_time', 'free_shipping', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('carriers')->insert([
        'name' => 'DHL',
        'transit_time' => '3-5 days',
    ]);

    $this->assertDatabaseHas('carriers', [
        'name' => 'DHL',
        'free_shipping' => false,
        'status' => true,
    ]);
});
