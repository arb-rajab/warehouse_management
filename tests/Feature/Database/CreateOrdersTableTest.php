<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('orders has the expected columns', function () {
    expect(Schema::hasTable('orders'))->toBeTrue();
    expect(Schema::hasColumns('orders', [
        'id', 'combined_order_id', 'user_id', 'guest_id', 'seller_id', 'shipping_address',
        'additional_info', 'shipping_type', 'pickup_point_id', 'carrier_id', 'delivery_status',
        'payment_type', 'payment_status', 'payment_details', 'grand_total', 'coupon_discount',
        'code', 'tracking_code', 'date', 'viewed', 'delivery_viewed', 'payment_status_viewed',
        'commission_calculated', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('orders')->insert([
        'shipping_type' => 'home_delivery',
        'date' => now()->timestamp,
    ]);

    $this->assertDatabaseHas('orders', [
        'shipping_type' => 'home_delivery',
        'pickup_point_id' => 0,
        'delivery_status' => 'pending',
        'payment_status' => 'unpaid',
        'viewed' => 0,
        'delivery_viewed' => 1,
        'payment_status_viewed' => 1,
        'commission_calculated' => 0,
    ]);
});
