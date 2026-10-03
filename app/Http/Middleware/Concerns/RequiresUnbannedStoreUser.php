<?php

namespace App\Http\Middleware\Concerns;

use App\Models\StoreUser;
use Illuminate\Http\Request;

/**
 * The one role-and-ban check behind the store's role gates (IsAdmin,
 * IsCustomer, IsUser, IsSeller, IsDriver, IsDeliveryBoy).
 *
 * The gates compared `user_type` with `==` and only IsSeller looked at
 * `banned`, so a banned admin, customer or driver kept every route their role
 * opens for as long as their session or token lived.
 */
trait RequiresUnbannedStoreUser
{
    /**
     * Whether the request's user is a store user of one of the given types
     * that has not been banned. The type comparison is strict.
     */
    protected function isUnbannedStoreUserOfType(Request $request, string ...$userTypes): bool
    {
        $user = $request->user();

        return $user instanceof StoreUser
            && ! $user->banned
            && in_array($user->user_type, $userTypes, true);
    }
}
