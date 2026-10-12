<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\StoreUser;

/**
 * Which coupons a store seller may change or delete: their own. The seller
 * API's and the seller area's coupon actions took any coupon by id.
 */
class CouponPolicy
{
    public function update(StoreUser $user, Coupon $coupon): bool
    {
        return $coupon->user_id === $user->id;
    }

    public function delete(StoreUser $user, Coupon $coupon): bool
    {
        return $this->update($user, $coupon);
    }
}
