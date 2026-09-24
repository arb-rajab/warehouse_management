<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\Warehouse\OrderCollection;
use App\Http\Resources\V2\Warehouse\OrderDetailResource;
use App\Http\Resources\V2\Warehouse\OrderItemResource;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Http\Request;



class OrderController extends Controller
{
    public function getOrdersList()
    {
     $orders = Order::where('grand_total', '!=', 0)->get();
    return new OrderCollection($orders);
    }

    public function getOrderDetails($id)
    {
        $order = Order::find($id);
        if ($order == null) {
            return response()->json([
                'result' => false,
                'message' => 'Order not found',
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
                'message' => 'Order not found',
                'status' => 404
            ]);
        }
        $order_detail = OrderDetail::where('order_id', $order->id)->get();
        return  OrderItemResource::collection($order_detail);
    }

    public function updateOrderStatus(Request $request)
    {
        $id = $request->id;
        $status = $request->status;
        $otajerOrderID = $request->otajerOrderID;
        $order = Order::find($id);
        if ($order != null) {
            if ($status == 'fetched') {
                $order->delivery_status = 'confirmed';
                $order->wms_status = 'Sent to WMS';
                $order->save();
            } elseif ($status == 'sent') {
                if ($otajerOrderID) {
                    $order->delivery_status = 'ready_for_delivery';
                    $order->wms_status = 'Sent to Otajer';
                    $order->otajerOrderID = $otajerOrderID;
                    $order->save();
                } else {
                    return response()->json([
                        'result' => false,
                        'message' => 'otajerOrderID is missing in the request body',
                        'status' => 200
                    ]);
                }
            } else {
                return response()->json([
                    'result' => false,
                    'message' => 'status must be `fetched` or `sent` ',
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'result' => false,
                'message' => 'Order not found',
                'status' => 404
            ]);
        }

        return response()->json([
            'result' => true,
            'message' => 'Order Updated Successfully',
            'status' => 200
        ]);
    }

    public function customer_update(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $customer = User::findOrFail($request->customer_id);

        if (isset($order)) {
            if ($order->by_rep) {
                $order->for_customer = $request->customer_id;
                $order->member_serial = $customer->member_serial;
            } else {
                $order->user_id = $request->customer_id;
            }
            $order->save();
        }
    }
}
