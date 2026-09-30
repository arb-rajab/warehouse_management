<?php

use App\Models\Coupon;
use App\Policies\CouponPolicy;

test('a seller may change their own coupon and not another seller\'s', function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $coupon = Coupon::create([
        'user_id' => $seller->id, 'type' => 'cart_base', 'code' => 'SAVE5', 'details' => '{}',
        'discount' => 5, 'discount_type' => 'amount', 'start_date' => now()->timestamp, 'end_date' => now()->addDay()->timestamp,
    ])->fresh();

    $policy = new CouponPolicy;

    expect($policy->update($seller, $coupon))->toBeTrue()
        ->and($policy->update($otherSeller, $coupon))->toBeFalse();
});
