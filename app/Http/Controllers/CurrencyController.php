<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function changeCurrency(Request $request)
    {
        $currency = Currency::where('code', $request->currency_code)->first();
        $request->session()->put('currency_code', $request->currency_code);
        $request->session()->put('currency_symbol', $currency->symbol);
        $request->session()->put('currency_exchange_rate', $currency->exchange_rate);
    	flash(translate('Currency changed to ').$currency->name)->success();
    }

}
