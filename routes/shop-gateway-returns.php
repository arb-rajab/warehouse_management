<?php

use App\Http\Controllers\Payment\AamarpayController;
use App\Http\Controllers\Payment\IyzicoController;
use App\Http\Controllers\Payment\SslcommerzController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gateway returns that arrive as a cross-site POST
|--------------------------------------------------------------------------
|
| SSLCommerz, AamarPay and iyzico send the customer back with a POST from
| their own site. With SESSION_SAME_SITE=lax that POST carries no session
| cookie, and answering it from the `web` group started a fresh session whose
| cookie overwrote the customer's and signed them out. So these routes run
| without the `web` group: no session, no cookies, no CSRF check (they cannot
| carry a token). Each is authenticated by the reference recorded when the
| payment started plus the gateway's server-side verification
| (App\Services\GatewayReturn), and answers with a 303 to a GET in the `web`
| group. RouteServiceProvider mounts this file under /temp with
| `throttle:gateway-returns`; `payment.gateway:<name>` is added per route.
| See .ai/rules/payment-gateway-returns.md before adding a route here.
|
*/

Route::controller(SslcommerzController::class)->group(function () {
    Route::post('/sslcommerz/success', 'success')->name('sslcommerz.success');
    Route::post('/sslcommerz/fail', 'fail')->name('sslcommerz.fail');
    Route::post('/sslcommerz/cancel', 'cancel')->name('sslcommerz.cancel');
});

Route::controller(AamarpayController::class)->group(function () {
    Route::post('/aamarpay/success', 'success')->name('aamarpay.success');
    Route::post('/aamarpay/fail', 'fail')->name('aamarpay.fail');
});

// The URL's segments are what the old callback trusted; they are ignored now.
Route::any('/iyzico/payment/callback/{payment_type}/{amount?}/{payment_method?}/{combined_order_id?}/{customer_package_id?}/{seller_package_id?}', [IyzicoController::class, 'callback'])->name('iyzico.callback');
