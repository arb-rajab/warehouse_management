<?php

namespace App\Http\Middleware;

use App\Support\StorePaymentGateways;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a payment gateway's route (its checkout, its callbacks) with a 404
 * unless the gateway is enabled: chosen on the Payment methods page, else
 * listed in STORE_PAYMENT_GATEWAYS (StorePaymentGateways::enabled()).
 *
 * The gateway routes are unauthenticated callbacks that finish orders, wallet
 * top-ups and packages, and they were reachable whether or not the store used
 * the gateway. Nothing is enabled by default; cash on delivery and the manual
 * payment methods are not gateways and are not affected. Attached to every
 * route of a gateway controller by RouteServiceProvider.
 */
class EnsurePaymentGatewayEnabled
{
    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        abort_unless(StorePaymentGateways::isEnabled($gateway), 404);

        return $next($request);
    }
}
