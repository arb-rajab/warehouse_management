<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\StoreUser;

/**
 * Which coupons a store seller may change: their own. The seller API's
 * coupon update took any coupon by id.
 */
class CouponPolicy
{
    public function update(StoreUser $user, Coupon $coupon): bool
    {
        return $coupon->user_id === $user->id;
    }
}
