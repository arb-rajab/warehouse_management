<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\RefundRequestCollection;
use App\Models\ClubPoint;
use App\Models\OrderDetail;
use App\Models\RefundRequest;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{

    public function get_list()
    {
        $refunds = RefundRequest::with(RefundRequest::WITH_LISTED)->where('user_id', auth()->user()->id)->latest()->paginate(10);

        return new RefundRequestCollection($refunds);
    }

    public function send(Request $request)
    {
        $order_detail = OrderDetail::where('id', $request->id)
            ->whereHas('order', fn ($order) => $order->where('user_id', auth()->user()->id))
            ->where('delivery_status', 'delivered')
            ->where('payment_status', 'paid')
            ->firstOrFail();

        $has_open_refund = RefundRequest::where('order_detail_id', $order_detail->id)
            ->where('seller_approval', '!=', 2)
            ->where('admin_approval', '!=', 2)
            ->exists();

        if ($has_open_refund) {
            return response()->json([
                'success' => false,
                'message' => __('api.refund.already_requested')
            ], 422);
        }

        $refund = new RefundRequest;
        $refund->user_id = auth()->user()->id;
        $refund->order_id = $order_detail->order_id;
        $refund->order_detail_id = $order_detail->id;
        $refund->seller_id = $order_detail->seller_id;
        $refund->seller_approval = 0;
        $refund->reason = $request->reason;
        $refund->admin_approval = 0;
        $refund->admin_seen = 0;
        $refund->refund_amount = $order_detail->price + $order_detail->tax;
        $refund->refund_status = 0;
        $refund->save();

        return response()->json([
            'success' => true,
            'message' => __('api.refund.request_sent')
        ]);


    }
}
