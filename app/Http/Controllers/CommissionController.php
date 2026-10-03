<?php

namespace App\Http\Controllers;

use App\Models\CommissionHistory;
use App\Models\Payment;
use App\Models\SellerWithdrawRequest;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Session;

class CommissionController extends Controller
{
    //redirect to payment controllers according to selected payment gateway for seller payment
    public function pay_to_seller(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_option' => ['required', 'string', 'max:100'],
            'payment_withdraw' => ['nullable', 'in:withdraw_request'],
            'withdraw_request_id' => ['required_if:payment_withdraw,withdraw_request', 'nullable', 'integer', 'exists:seller_withdraw_requests,id'],
            'txn_code' => ['nullable', 'string', 'max:191'],
        ]);

        $data = [
            'shop_id' => $validated['shop_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_option'],
            'payment_withdraw' => $validated['payment_withdraw'] ?? null,
            'withdraw_request_id' => $validated['withdraw_request_id'] ?? null,
            'txn_code' => $validated['txn_code'] ?? null,
        ];

        $request->session()->put('payment_type', 'seller_payment');
        $request->session()->put('payment_data', $data);

        if ($validated['payment_option'] == 'cash' || $validated['payment_option'] == 'bank_payment') {
            return $this->seller_payment_done($data, null);
        }

        $payment_data = $data;

        try {
            DB::transaction(function () use ($payment_data) {
                $shop = Shop::query()->lockForUpdate()->findOrFail($payment_data['shop_id']);
                $shop->increment('admin_to_pay', $payment_data['amount']);

                $payment = new Payment;
                $payment->seller_id = $shop->user_id;
                $payment->amount = $payment_data['amount'];
                $payment->payment_method = 'Seller paid to admin';
                $payment->txn_code = $payment_data['txn_code'];
                $payment->payment_details = null;
                $payment->save();
            });
        } finally {
            Session::forget('payment_data');
            Session::forget('payment_type');
        }

        flash(translate('Payment completed'))->success();
        return redirect()->route('sellers.index');
    }

    //redirects to this method after successfull seller payment
    public function seller_payment_done($payment_data, $payment_details){
        $isWithdrawRequest = $payment_data['payment_withdraw'] == 'withdraw_request';

        try {
            DB::transaction(function () use ($payment_data, $payment_details, $isWithdrawRequest) {
                $shop = Shop::query()->lockForUpdate()->findOrFail($payment_data['shop_id']);

                if ($isWithdrawRequest) {
                    $seller_withdraw_request = SellerWithdrawRequest::query()
                        ->lockForUpdate()
                        ->where('user_id', $shop->user_id)
                        ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 0))
                        ->findOrFail($payment_data['withdraw_request_id']);
                    $seller_withdraw_request->status = '1';
                    $seller_withdraw_request->viewed = '1';
                    $seller_withdraw_request->save();
                }

                $shop->decrement('admin_to_pay', $payment_data['amount']);

                $payment = new Payment;
                $payment->seller_id = $shop->user_id;
                $payment->amount = $payment_data['amount'];
                $payment->payment_method = $payment_data['payment_method'];
                $payment->txn_code = $payment_data['txn_code'];
                $payment->payment_details = $payment_details;
                $payment->save();
            });
        } finally {
            Session::forget('payment_data');
            Session::forget('payment_type');
        }

        flash(translate('Payment completed'))->success();
        return redirect()->route($isWithdrawRequest ? 'withdraw_requests_all' : 'sellers.index');
    }

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
