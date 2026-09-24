<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartService
{
    public function checkRepVisit()
    {
        $auth_user = Auth::user();
        $result = [];
        $result['message'] = '';
        $result['status'] = '';

        if ($auth_user && !$auth_user->is_rep) {
            return $result;
        }

        if (!$auth_user->has_open_visit()) {
            $result['message'] = 'Please make a visit first';
            $result['status'] = 'warning';
            return $result;
        }

        $result['message'] = 'Success';
        $result['status'] = 'success';
        return $result;
    }

    public function setVisitCustomerToCart()
    {
        $auth_user = Auth::user();
        $result = [];
        $result['message'] = '';
        $result['status'] = '';

        $visit = $auth_user->rep_last_open_visit();

        $cart = Cart::orderBy('created_at', 'desc')->where('user_id', $auth_user->id)->first();

        Log::info($visit);
        Log::info($cart);
        $cart->for_customer = $visit->customer_id;
        $cart->by_rep = 1;
        $cart->save();

        $result['message'] = 'Success';
        $result['status'] = 'success';
        return $result;
    }
}
