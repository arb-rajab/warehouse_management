<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderDetail;
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

    /**
     * Who may download a digital product's file through the app API: a store
     * admin, the seller who uploaded it, and a customer with a paid order for
     * it. The endpoint's purchase check tested a query's Collection for
     * truthiness, which an empty one also passes, so any signed-in account
     * could download any paid digital product.
     */
    public function download(StoreUser $user, Product $product): bool
    {
        return $user->user_type === 'admin'
            || $product->user_id === $user->id
            || OrderDetail::where('product_id', $product->id)
                ->where('payment_status', 'paid')
                ->whereIn('order_id', Order::select('id')->where('user_id', $user->id))
                ->exists();
    }
}
