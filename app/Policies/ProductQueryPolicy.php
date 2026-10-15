<?php

namespace App\Policies;

use App\Models\ProductQuery;
use App\Models\StoreUser;

class ProductQueryPolicy
{
    /**
     * The back-office form accepts the seller or staff on an unanswered query.
     * Called directly so Gate::before cannot bypass these record invariants.
     */
    public function reply(StoreUser $user, ProductQuery $query): bool
    {
        return ($query->reply === null || $query->reply === '') && ($user->id === $query->seller_id || $user->user_type === 'staff');
    }
}
