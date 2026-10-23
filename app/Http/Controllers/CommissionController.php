<?php

namespace App\Http\Controllers;

use App\Models\CommissionHistory;

class CommissionController extends Controller
{
    //calculate seller commission after payment
    public function calculateCommission($order){
        if ($order->payment_type == 'cash_on_delivery') {
            foreach ($order->orderDetails as $orderDetail) {
                $orderDetail->payment_status = 'paid';
                $orderDetail->save();
                $commission_percentage = 0;

                if(get_setting('vendor_commission_activation')){
                    if (get_setting('category_wise_commission')) {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = get_setting('vendor_commission');
                    }
                }
                if ($orderDetail->product->user->user_type == 'seller') {
                    $seller = $orderDetail->product->user->shop;
                    $admin_commission = ($orderDetail->price * $commission_percentage) / 100;

                    if (get_setting('product_manage_by_admin') == 1) {
                        $seller_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                        $seller->admin_to_pay += $seller_earning;
                    } else {
                        $seller_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                        $seller->admin_to_pay -= $admin_commission;
                    }

                    $seller->save();

                    $commission_history = new CommissionHistory;
                    $commission_history->order_id = $order->id;
                    $commission_history->order_detail_id = $orderDetail->id;
                    $commission_history->seller_id = $orderDetail->seller_id;
                    $commission_history->admin_commission = $admin_commission;
                    $commission_history->seller_earning = $seller_earning;

                    $commission_history->save();
                }
            }
        }
        else{
            foreach ($order->orderDetails as $orderDetail) {
                $orderDetail->payment_status = 'paid';
                $orderDetail->save();
                $commission_percentage = 0;

                if(get_setting('vendor_commission_activation')){
                    if (get_setting('category_wise_commission')) {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = get_setting('vendor_commission');
                    }
                }

                if ($orderDetail->product->user->user_type == 'seller') {
                    $seller = $orderDetail->product->user->shop;
                    $admin_commission = ($orderDetail->price * $commission_percentage)/100;

                    if (get_setting('product_manage_by_admin') == 1) {
                        $seller_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                        $seller->admin_to_pay += $seller_earning;
                    } else {
                        $seller_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                        $seller->admin_to_pay += $seller_earning;
                    }
                    $seller->save();

                    $commission_history = new CommissionHistory;
                    $commission_history->order_id = $order->id;
                    $commission_history->order_detail_id = $orderDetail->id;
                    $commission_history->seller_id = $orderDetail->seller_id;
                    $commission_history->admin_commission = $admin_commission;
                    $commission_history->seller_earning = $seller_earning;

                    $commission_history->save();
                }
            }
            if($order->shop != null){
                $seller->admin_to_pay -= $order->coupon_discount;
                $seller->save();
            }
        }
    }
}
