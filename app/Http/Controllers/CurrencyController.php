<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurrencyController extends Controller
{
    public function changeCurrency(Request $request): Response
    {
        $currency = Currency::query()->where('code', $request->input('currency_code'))->firstOrFail();
        $request->session()->put('currency_code', $currency->code);
        $request->session()->put('currency_symbol', $currency->symbol);
        $request->session()->put('currency_exchange_rate', $currency->exchange_rate);
        flash(__('storefront.common.currency_changed', ['currency' => $currency->name]))->success();

        return response('');
    }
}
