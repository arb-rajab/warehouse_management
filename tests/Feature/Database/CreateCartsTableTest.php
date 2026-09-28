<?php

use Illuminate\Support\Facades\Schema;

test('carts has the expected columns', function () {
    expect(Schema::hasTable('carts'))->toBeTrue();
    expect(Schema::hasColumns('carts', [
        'id', 'owner_id', 'user_id', 'temp_user_id', 'address_id', 'product_id', 'variation',
        'price', 'tax', 'shipping_cost', 'shipping_type', 'pickup_point', 'carrier_id', 'discount',
        'product_referral_code', 'coupon_code', 'coupon_applied', 'quantity', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
