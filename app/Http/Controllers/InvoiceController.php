<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\StoreLocale;
use Illuminate\Support\Facades\Gate;
use PDF;

class InvoiceController extends Controller
{
    //download invoice
    public function invoice_download($id)
    {
        $order = Order::with('orderDetails.product')->findOrFail($id);
        Gate::authorize('downloadInvoice', $order);

        $documentLocale = StoreLocale::documentLocale();
        $direction = $documentLocale === 'ar' ? 'rtl' : 'ltr';
        $text_align = $direction === 'rtl' ? 'right' : 'left';
        $not_text_align = $direction === 'rtl' ? 'left' : 'right';
        $font_family = 'dejavusans';

        $config = [];

        $cartResults = [];

        foreach ($order->orderDetails as $cart) {
            if (isset($cart->variation_qty) && $cart->variation_qty != 'null') {
                $jsonData = $cart->variation_qty;

                $variationData = json_decode($jsonData, true);
                $cartResults[$cart->product_serial] = $variationData;
            }
        }

        return PDF::loadView('shop.backend.invoices.invoice', [
            'order' => $order,
            'documentLocale' => $documentLocale,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'cartResults' => $cartResults,
        ], [], $config)->download('order-' . $order->code . '.pdf');
    }
}
