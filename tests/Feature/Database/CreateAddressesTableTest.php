<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('addresses has the expected columns', function () {
    expect(Schema::hasTable('addresses'))->toBeTrue();
    expect(Schema::hasColumns('addresses', [
        'id', 'user_id', 'address', 'country_id', 'state_id', 'city_id',
        'longitude', 'latitude', 'postal_code', 'phone', 'set_default',
        'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('addresses')->insert([
        'user_id' => 1,
        'address' => '123 Main St',
        'state_id' => 1,
        'postal_code' => '1000AA',
        'phone' => '0600000000',
    ]);

    $this->assertDatabaseHas('addresses', [
        'user_id' => 1,
        'set_default' => 0,
    ]);
});
