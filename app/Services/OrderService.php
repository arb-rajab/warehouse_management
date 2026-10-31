<?php
namespace App\Services;

use App\Models\Order;

use App\Models\ProductStock;
use App\Models\SmsTemplate;
use App\Models\StoreUser;
use App\Utility\NotificationUtility;
use App\Utility\SmsUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class OrderService{

    /**
     * @var list<string>
     */
    public const DELIVERY_STATUSES = ['pending', 'confirmed', 'picked_up', 'on_the_way', 'on_delivery', 'ready_for_delivery', 'delivered', 'cancelled'];

    /**
     * The order, for a seller: one that has a line of theirs. Another seller's
     * order is not found, whatever id is sent.
     */
    public function findForSeller(int|string $orderId, int $sellerId): Order
    {
        return Order::whereHas('orderDetails', fn ($lines) => $lines->where('seller_id', $sellerId))->findOrFail($orderId);
    }

    /**
     * The seller API only checked that someone was signed in, so a customer's
     * token could reach the non-seller branch below, which finds any order.
     */
    private function abortUnlessOrderStaff(): void
    {
        abort_unless(in_array(auth()->user()->user_type, ['seller', 'admin', 'staff', 'delivery_boy'], true), 403);
    }

    public function handle_delivery_status(Request $request)
    {
        $this->abortUnlessOrderStaff();
        $request->validate([
            'order_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(self::DELIVERY_STATUSES)],
        ]);

        $order = DB::transaction(function () use ($request): Order {
            $isSeller = auth()->user()->user_type == 'seller';
            $found = $isSeller ? $this->findForSeller($request->order_id, auth()->user()->id) : Order::findOrFail($request->order_id);

            // The row lock serialises two cancel requests for one order, so the
            // second one reads the status the first wrote and refunds nothing.
            $order = Order::query()->lockForUpdate()->findOrFail($found->id);
            $alreadyCancelled = $order->delivery_status == 'cancelled';
            $order->delivery_viewed = '0';
            $order->delivery_status = $request->status;
            $order->save();

            if ($request->status == 'cancelled' && ! $alreadyCancelled && $order->payment_type == 'wallet') {
                StoreUser::query()->whereKey($order->user_id)->lockForUpdate()->increment('balance', $order->grand_total);
            }

            $lines = $isSeller ? $order->orderDetails->where('seller_id', auth()->user()->id) : $order->orderDetails;

            foreach ($lines as $key => $orderDetail) {

                $orderDetail->delivery_status = $request->status;
                $orderDetail->save();

                if ($request->status == 'cancelled' && ! $alreadyCancelled) {
                    product_restock($orderDetail);
                }

                if (addon_is_activated('affiliate_system') && auth()->user()->user_type == 'admin') {
                    if (($request->status == 'delivered' || $request->status == 'cancelled') &&
                        $orderDetail->product_referral_code
                    ) {

                        $no_of_delivered = 0;
                        $no_of_canceled = 0;

                        if ($request->status == 'delivered') {
                            $no_of_delivered = $orderDetail->quantity;
                        }
                        if ($request->status == 'cancelled') {
                            $no_of_canceled = $orderDetail->quantity;
                        }

                        $referred_by_user = StoreUser::where('referral_code', $orderDetail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, 0, $no_of_delivered, $no_of_canceled);
                    }
                }
            }

            return $order;
        });

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'delivery_status_change')->first()->status == 1) {
            try {
                SmsUtility::delivery_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {

            }
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            NotificationUtility::sendLocalizedOrderUpdate($order, $order->delivery_status);
        }


        if (addon_is_activated('delivery_boy')) {
            if (auth()->user()->user_type == 'delivery_boy') {
                $deliveryBoyController = new DeliveryBoyController;
                $deliveryBoyController->store_delivery_history($order);
            }
        }
    }

    public function handle_payment_status(Request $request)
    {
        $this->abortUnlessOrderStaff();
        $order = auth()->user()->user_type == 'seller'
            ? $this->findForSeller($request->order_id, auth()->user()->id)
            : Order::findOrFail($request->order_id);
        $order->payment_status_viewed = '0';
        $order->save();

        if (auth()->user()->user_type == 'seller') {
            foreach ($order->orderDetails->where('seller_id', auth()->user()->id) as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        } else {
            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        }

        $status = 'paid';
        foreach ($order->orderDetails as $key => $orderDetail) {
            if ($orderDetail->payment_status != 'paid') {
                $status = 'unpaid';
            }
        }
        $order->payment_status = $status;
        $order->save();


        if ($order->payment_status == 'paid' && $order->commission_calculated == 0) {
            calculateCommissionAffilationClubPoint($order);
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            NotificationUtility::sendLocalizedOrderUpdate($order, $order->payment_status);
        }


        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {

            }
        }
        return 1;

    }

   public function checkForOtajerProblems($order)
{
    $otajer_is_down_user = StoreUser::where('name', 'like', '%' . 'otajer' . '%')->first();

    if ($order->member_serial == null) {
        return [
            'is_down' => true,
            'member_serial' => $otajer_is_down_user?->member_serial ?? '',
            'for_customer' => $otajer_is_down_user?->id ?? '',
        ];
    } else {
        return [
            'is_down' => false,
            'member_serial' => '',
            'for_customer' => '',
        ];
    }
}


    public function getDefaultRep(): StoreUser
    {
        return StoreUser::where('id', get_setting('default_representative_id', 0))->first();
    }

    public function applyDefaultRepLogic($order, $customer): void
    {
        $default_rep = $this->getDefaultRep();

        if($default_rep){
            $order->for_customer = $customer->id;
            $order->user_id = $default_rep->id;
            $order->by_rep = 1;
            $order->save();

            $default_rep->profits += $order->profits;
            $default_rep->save();
        }
    }
}
