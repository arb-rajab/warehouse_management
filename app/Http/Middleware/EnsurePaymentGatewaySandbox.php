<?php

namespace App\Http\Middleware;

use App\Support\StorePaymentGateways;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a gateway's sandbox-only test route with a 404 unless the gateway is
 * enabled, is in sandbox mode and the app is not running in production.
 *
 * PayHere's three `testing` routes build a signed payment form with a
 * made-up buyer and a fixed amount; they exist for a developer trying the
 * gateway's sandbox and must never answer on a live store. All three
 * conditions read the effective values: the enabled list and the mode saved on
 * the Payment methods page, else `.env` (StorePaymentGateways).
 *
 * `payment.gateway:<name>` is already on every route of the gateway's
 * controller (RouteServiceProvider); this adds the sandbox and environment
 * checks and repeats the enabled check so the route stays safe on its own.
 * Usage: `->middleware('payment.sandbox:payhere')`.
 */
class EnsurePaymentGatewaySandbox
{
    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        abort_unless(
            StorePaymentGateways::isEnabled($gateway)
                && StorePaymentGateways::mode($gateway) === 'sandbox'
                && ! app()->isProduction(),
            404,
        );

        return $next($request);
    }
}
