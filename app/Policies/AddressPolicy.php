<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\StoreUser;

/**
 * Which addresses a store account may open, change, delete, make its
 * default or ship a cart to: its own, and for a representative the ones of
 * the customer they are currently ordering for. The storefront's, the seller
 * area's and the app API's address actions took any address by id.
 */
class AddressPolicy
{
    public function manage(StoreUser $user, Address $address): bool
    {
        if ($address->user_id === $user->id) {
            return true;
        }

        $customerId = $user->is_rep ? $user->current_customer?->id : null;

        return $customerId !== null && $address->user_id === $customerId;
    }
}
