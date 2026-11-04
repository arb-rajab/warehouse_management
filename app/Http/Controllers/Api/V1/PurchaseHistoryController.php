<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PurchasedResource;
use App\Http\Resources\V1\PurchaseHistoryCollection;
use App\Http\Resources\V1\PurchaseHistoryItemsCollection;
use App\Http\Resources\V1\PurchaseHistoryMiniCollection;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PurchaseHistoryController extends Controller
{
    public function index(Request $request)
    {

    $user_id = auth()->user()->id;

        // The related accounts load only the columns the list reads.
        $order_query = Order::with(['user:id,name,AccSysID', 'rep:id,name,AccSysID'])
            // The orders the account placed and the ones a representative placed
            // for it, grouped so the filters below apply to both.
            ->where(fn ($query) => $query->where('user_id', $user_id)->orWhere('for_customer', $user_id));
        if ($request->payment_status != "" || $request->payment_status != null) {
            $order_query->where('payment_status', $request->payment_status);
        }
        if ($request->delivery_status != "" || $request->delivery_status != null) {
            $delivery_status = $request->delivery_status;
            $order_query->whereIn("id", function ($query) use ($delivery_status) {
                $query->select('order_id')
                    ->from('order_details')
                    ->where('delivery_status', $delivery_status);
            });
        }
        return new PurchaseHistoryMiniCollection($order_query->latest()->paginate(15));
    }

    public function details($id)
    {
        $order = Order::findOrFail($id);
        Gate::authorize('view', $order);
        $order->load(['user:id,name', 'rep:id,name']);

        return new PurchaseHistoryCollection($order->newCollection([$order]));
    }

    public function items($id)
    {
        $order = Order::findOrFail($id);
        Gate::authorize('view', $order);

        $order_query = OrderDetail::with(OrderDetail::WITH_ITEM_DETAILS)->where('order_id', $order->id);
        return new PurchaseHistoryItemsCollection($order_query->get());
    }

    public function digital_purchased_list()
    {


        $order_detail_products = OrderDetail::whereHas('order', function($q){
    		$q->where('payment_status', 'paid');
            $q->where('user_id', auth()->id());
		})->whereHas('product', fn ($query) => $query->where('digital', 1))->with(['product' => function($query){
            $query->where('digital', 1)->with('product_translations');
          }])
           ->paginate(15);

    //   $products = Product::with(['orderDetails', 'orderDetails.order' => function($q) {
    //          $q->where('payment_status', 'paid');
    //          $q->where('user_id', auth()->id());
    //     }])
    //     ->where('digital', 1)
    //     ->paginate(15);



        return PurchasedResource::collection($order_detail_products);
    }
}
