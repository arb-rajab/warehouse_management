<?php

use Illuminate\Support\Facades\Schema;

test('the shops table has the expected columns', function () {
    expect(Schema::hasTable('shops'))->toBeTrue();
    expect(Schema::hasColumns('shops', [
        'id', 'user_id', 'name', 'logo', 'sliders', 'phone', 'address', 'rating',
        'num_of_reviews', 'num_of_sale', 'seller_package_id', 'product_upload_limit',
        'package_invalid_at', 'verification_status', 'verification_info',
        'cash_on_delivery_status', 'admin_to_pay', 'facebook', 'instagram', 'google',
        'twitter', 'youtube', 'slug', 'meta_title', 'meta_description', 'pick_up_point_id',
        'shipping_cost', 'delivery_pickup_latitude', 'delivery_pickup_longitude',
        'bank_name', 'bank_acc_name', 'bank_acc_no', 'bank_routing_no', 'bank_payment_status',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});
