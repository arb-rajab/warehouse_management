<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Upload;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PurchaseHistoryController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user_id =  Auth::user()->id;

        $orders = Order::with('orderDetails')->where('user_id', $user_id)->orWhere('for_customer',  $user_id )->orderBy('code', 'desc')->paginate(10);

        return view('shop.frontend.user.purchase_history', compact('orders'));
    }

    public function digital_index()
    {
        $orders = DB::table('orders')
                        ->orderBy('code', 'desc')
                        ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                        ->join('products', 'order_details.product_id', '=', 'products.id')
                        ->where('orders.user_id', Auth::user()->id)
                        ->where('products.digital', '1')
                        ->where('order_details.payment_status', 'paid')
                        ->select('order_details.id')
                        ->paginate(15);
        return view('shop.frontend.user.digital_purchase_history', compact('orders'));
    }

    public function purchase_history_details($id)
    {
        $order = Order::with(['orderDetails' => fn ($details) => $details->with(OrderDetail::WITH_ITEM_DETAILS)])->findOrFail(decrypt($id));
        $order->delivery_viewed = 1;
        $order->payment_status_viewed = 1;
        $order->save();
        return view('shop.frontend.user.order_details_customer', compact('order'));
    }

    public function download(Request $request)
    {
        $product = Product::findOrFail(decrypt($request->id));
        $downloadable = OrderDetail::where('product_id', $product->id)
            ->where('payment_status', 'paid')
            ->whereHas('order', fn ($order) => $order->where('user_id', Auth::id()))
            ->exists();
        if ($downloadable) {
            $upload = Upload::findOrFail($product->file_name);
            if (config('filesystems.default') == "s3") {
                return \Storage::disk('s3')->download($upload->file_name, $upload->file_original_name . "." . $upload->extension);
            } else {
                if (file_exists(base_path('public/' . $upload->file_name))) {
                    return response()->download(base_path('public/' . $upload->file_name));
                }
            }
        } else {
            flash(__('storefront.product.you_cannot_download_this_product_at_this_product'))->success();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function order_cancel($id)
    {
        $order = Order::where('id', $id)->where('user_id', auth()->user()->id)->first();
        if($order && ($order->delivery_status == 'pending' && $order->payment_status == 'unpaid')) {
            $order->delivery_status = 'cancelled';
            $order->save();

            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->delivery_status = 'cancelled';
                $orderDetail->save();
                product_restock($orderDetail);
            }

            flash(__('storefront.checkout.order_has_been_canceled_successfully'))->success();
        } else {
            flash(__('storefront.common.something_went_wrong'))->error();
        }

        return back();
    }
}
