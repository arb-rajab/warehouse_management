<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\StoreUser;

/**
 * Which products a store seller may change: their own. The seller area's
 * product update, publish and feature actions and the seller API's product
 * update took any product by id, so any seller could reprice, restock or
 * unpublish another seller's products or the store's own.
 *
 * The ability is named for the store's sellers because `products` is this
 * app's table too, and nothing here decides what a warehouse user may do.
 */
class ProductPolicy
{
    public function updateAsSeller(StoreUser $user, Product $product): bool
    {
        return $product->user_id === $user->id;
    }
}
