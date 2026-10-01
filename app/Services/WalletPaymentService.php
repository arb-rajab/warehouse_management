<?php

namespace App\Services;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;

/**
 * Takes the price of an order out of the wallet of the customer who placed it.
 *
 * The mobile API's wallet payment used to charge the user named in the request
 * the amount named in the request, while the order itself was priced from the
 * cart and marked paid: anyone could pay with someone else's balance, or send
 * `amount=0` and pay nothing. The payer is the signed-in account and the amount
 * is the order's own total now, read and deducted under a row lock so two
 * payments cannot spend the same balance.
 */
class WalletPaymentService
{
    /**
     * Charge `$payer` the total of the combined order. When the balance does not
     * cover it, nothing is deducted and the order's parts are put back to unpaid.
     */
    public function charge(StoreUser $payer, int $combinedOrderId): bool
    {
        return DB::transaction(function () use ($payer, $combinedOrderId): bool {
            $account = StoreUser::query()->whereKey($payer->id)->lockForUpdate()->firstOrFail();
            $total = (float) CombinedOrder::query()
                ->whereKey($combinedOrderId)
                ->where('user_id', $payer->id)
                ->value('grand_total');

            if ($account->balance < $total) {
                Order::query()->where('combined_order_id', $combinedOrderId)->update(['payment_status' => 'unpaid']);

                return false;
            }

            $account->balance -= $total;
            $account->save();

            return true;
        });
    }
}
