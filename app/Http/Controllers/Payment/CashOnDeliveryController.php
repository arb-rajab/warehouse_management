<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;

class CashOnDeliveryController extends Controller
{
    public function pay($request = null){
        flash(__('storefront.checkout.your_order_has_been_placed_successfully'))->success();
        if($request?->session()->get('delete_product')){
            flash(__('storefront.cart.some_products_have_been_deleted_from_your_cart_for_more_details_check_the_order_notes'))->warning();

            // Delete the session item
            $request->session()->forget('delete_product');
        }
        return redirect()->route('order_confirmed');
    }
}
