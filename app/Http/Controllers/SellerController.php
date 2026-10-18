<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SellerController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:pay_to_seller', only: ['payment_modal'])];
    }

    public function payment_modal(Request $request)
    {
        $shop = Shop::findOrFail($request->id);

        return view('shop.backend.sellers.payment_modal', compact('shop'));
    }
}
