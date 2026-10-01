<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('pickup_points has the expected columns', function () {
    expect(Schema::hasTable('pickup_points'))->toBeTrue();
    expect(Schema::hasColumns('pickup_points', [
        'id', 'staff_id', 'name', 'address', 'phone', 'pick_up_status',
        'cash_on_pickup_status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('pickup_points')->insert([
        'staff_id' => 1,
        'name' => 'Main Warehouse',
        'address' => '123 Some Street',
        'phone' => '0612345678',
    ]);

    $this->assertDatabaseHas('pickup_points', [
        'staff_id' => 1,
        'name' => 'Main Warehouse',
        'phone' => '0612345678',
    ]);
});
