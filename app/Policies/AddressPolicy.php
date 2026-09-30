<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\StoreUser;

/**
 * Which addresses a store account may open, change, delete or make its
 * default: its own. The storefront's and the seller area's address actions
 * took any address by id.
 */
class AddressPolicy
{
    public function manage(StoreUser $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }
}
