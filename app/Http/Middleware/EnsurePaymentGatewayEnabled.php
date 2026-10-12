<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a payment gateway's route (its checkout, its callbacks) with a 404
 * unless the gateway is listed in config('store.enabled_payment_gateways').
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
        abort_unless(in_array($gateway, config('store.enabled_payment_gateways', []), true), 404);

        return $next($request);
    }
}
