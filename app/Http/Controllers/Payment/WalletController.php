<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use App\Services\WalletPaymentService;
use Illuminate\Support\Facades\DB;
use Auth;
use Session;

class WalletController extends Controller
{
    public function pay(){
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $user = Auth::user();
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                // The balance is read and deducted under a row lock, for the account's own order only,
                // and the deduction is undone unless the order is marked paid in the same transaction
                // (an order that is already paid is not paid, or charged, twice).
                DB::beginTransaction();
                $payment = new VerifiedPayment('wallet', 'combined-order-'.$combined_order->id, (float) $combined_order->grand_total);
                if (app(WalletPaymentService::class)->charge($user, $combined_order->id)
                    && app(GatewayPaymentService::class)->complete($payment, $user->id, GatewayPaymentService::CART, $combined_order->id)) {
                    DB::commit();
                    Session::put('combined_order_id', $combined_order->id);
                    return redirect()->route('order_confirmed');
                }
                DB::rollBack();

                flash(__('storefront.checkout.insufficient_wallet_balance'))->warning();
                return redirect()->route('checkout.payment_info');
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = $customer_package->amount;
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = $seller_package->amount;
            }
        }
    }
}
