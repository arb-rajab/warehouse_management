<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('order_details has the expected columns', function () {
    expect(Schema::hasTable('order_details'))->toBeTrue();
    expect(Schema::hasColumns('order_details', [
        'id', 'order_id', 'seller_id', 'product_id', 'variation', 'price', 'tax',
        'shipping_cost', 'quantity', 'payment_status', 'delivery_status', 'shipping_type',
        'pickup_point_id', 'product_referral_code', 'earn_point', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('order_details')->insert([
        'order_id' => 1,
        'product_id' => 1,
    ]);

    $this->assertDatabaseHas('order_details', [
        'order_id' => 1,
        'product_id' => 1,
        'tax' => 0.00,
        'shipping_cost' => 0.00,
        'payment_status' => 'unpaid',
        'delivery_status' => 'pending',
        'earn_point' => 0.00,
    ]);
});
