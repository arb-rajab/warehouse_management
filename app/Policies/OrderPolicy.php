<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\StoreUser;

/**
 * Who may see a store order. The invoice routes (the storefront's, which the
 * back office links too, and the seller area's) and the app API's purchase
 * history used to find the order by its id alone, so any signed-in store
 * account could read anyone's by counting through ids.
 */
class OrderPolicy
{
    /**
     * The permissions that gate the back office's order listings and order
     * page. Every listing row links its order's invoice.
     *
     * @var list<string>
     */
    private const ORDER_VIEWING_PERMISSIONS = [
        'view_all_orders',
        'view_inhouse_orders',
        'view_seller_orders',
        'view_pickup_point_orders',
        'view_order_details',
    ];

    /**
     * The account that placed the order, and the customer a representative
     * placed it for: both find it in their purchase history.
     */
    public function view(StoreUser $user, Order $order): bool
    {
        return $order->user_id === $user->id
            || $order->for_customer === $user->id;
    }

    public function downloadInvoice(StoreUser $user, Order $order): bool
    {
        return $this->view($user, $order)
            || $order->seller_id === $user->id
            || $user->canAny(self::ORDER_VIEWING_PERMISSIONS);
    }
}
