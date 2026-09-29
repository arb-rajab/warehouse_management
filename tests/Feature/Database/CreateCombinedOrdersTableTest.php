<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('combined_orders has the expected columns', function () {
    expect(Schema::hasTable('combined_orders'))->toBeTrue();
    expect(Schema::hasColumns('combined_orders', [
        'id', 'user_id', 'shipping_address', 'grand_total', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('combined_orders')->insert([
        'user_id' => 1,
    ]);

    $this->assertDatabaseHas('combined_orders', [
        'user_id' => 1,
        'grand_total' => 0.00,
    ]);
});
