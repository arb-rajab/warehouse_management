<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;

class CashOnDeliveryController extends Controller
{
    public function pay($request = null){
        flash(translate("Your order has been placed successfully"))->success();
        if($request?->session()->get('delete_product')){
            flash(translate('Some products have been deleted from your cart. For more details check the order notes.'))->warning();

            // Delete the session item
            $request->session()->forget('delete_product');
        }
        return redirect()->route('order_confirmed');
    }
}
