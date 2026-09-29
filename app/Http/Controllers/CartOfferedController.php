<?php

namespace App\Http\Controllers;

use App\Models\CartOffered;
use Auth;
use Illuminate\Http\Request;

class CartOfferedController extends Controller
{
    public function postpone(){
        $user = Auth::user();
        $carts = CartOffered::where('user_id', $user->id)->get();
        foreach ($carts as $cart) {
            $cart->is_postponed = 1;
            $cart->save();
        }

        return 1;
    }

    public function removeOffersFromCart()
    {
        $user = Auth::user();
        $carts = CartOffered::where('user_id', $user->id)->get()->load('product', 'product.offers');
        $user->offers()->detach($carts[0]->offer_id);
        foreach($carts as $cart){
            $cart->forceDelete();
        }
        return redirect()->back();
    }

}
