<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\UpdateWarehouseOrderCustomerRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseOrderStatusRequest;
use App\Http\Requests\Warehouse\WarehouseListRequest;
use App\Http\Resources\V2\Warehouse\OrderCollection;
use App\Http\Resources\V2\Warehouse\OrderDetailResource;
use App\Http\Resources\V2\Warehouse\OrderItemResource;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\StoreUser;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function getOrdersList(WarehouseListRequest $request)
    {
        $orders = Order::where('grand_total', '!=', 0)
            ->orderBy('id')
            ->paginate($request->perPage());

        return new OrderCollection($orders);
    }

    public function getOrderDetails($id)
    {
        $order = Order::find($id);
        if ($order == null) {
            return response()->json([
                'result' => false,
                'message' => __('api.order.not_found'),
                'status' => 404
            ]);
        }
        return new OrderDetailResource($order);
    }

    public function getOrderItems($id)
    {
        $order = Order::find($id);
        if ($order == null) {
            return response()->json([
                'result' => false,
                'message' => __('api.order.not_found'),
                'status' => 404
            ]);
        }

        // OrderItemResource reads the order, its sibling lines and each line's
        // product; handing it all three loaded keeps it to two queries however
        // many lines the order has.
        $order_detail = OrderDetail::where('order_id', $order->id)
            ->with('product:id,serial,name,unit_equal,api_unitId')
            ->get();
        $order->setRelation('orderDetails', $order_detail);
        $order_detail->each(fn (OrderDetail $line) => $line->setRelation('order', $order));

        return OrderItemResource::collection($order_detail);
    }

    public function updateOrderStatus(UpdateWarehouseOrderStatusRequest $request)
    {
        $order = Order::find($request->input('id'));
        if ($order == null) {
            return response()->json([
                'result' => false,
                'message' => __('api.order.not_found'),
                'status' => 404
            ]);
        }

        if ($request->input('status') === 'fetched') {
            $order->delivery_status = 'confirmed';
            $order->wms_status = 'Sent to WMS';
        } else {
            $order->delivery_status = 'ready_for_delivery';
            $order->wms_status = 'Sent to Otajer';
            $order->otajerOrderID = $request->input('otajerOrderID');
        }
        $order->save();

        return response()->json([
            'result' => true,
            'message' => __('api.order.updated'),
            'status' => 200
        ]);
    }

    public function customer_update(UpdateWarehouseOrderCustomerRequest $request)
    {
        $order = Order::findOrFail($request->integer('order_id'));
        $customer = StoreUser::findOrFail($request->integer('customer_id'));

        if ($customer->user_type !== 'customer') {
            return response()->json([
                'result' => false,
                'message' => __('api.account.not_customer'),
                'status' => 422
            ], 422);
        }

        $previous = ['user_id' => $order->user_id, 'for_customer' => $order->for_customer];

        if ($order->by_rep) {
            $order->for_customer = $customer->id;
            $order->member_serial = $customer->member_serial;
        } else {
            $order->user_id = $customer->id;
        }
        $order->save();

        Log::info('warehouse.order.customer_updated', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'by_rep' => $order->by_rep,
            'previous' => $previous,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'result' => true,
            'message' => __('api.order.customer_updated'),
            'status' => 200
        ]);
    }
}
