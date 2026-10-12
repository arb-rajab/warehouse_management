<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The delivery-boy app's routes: a signed-in, unbanned delivery boy only. They
 * were open to any signed-in account, which could read a delivery boy's
 * earnings and collections, mark any order delivered (paid, for cash on
 * delivery, with a commission credited every time) or put in a cancellation
 * request.
 */
class IsDeliveryBoy
{
    use RequiresUnbannedStoreUser;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isUnbannedStoreUserOfType($request, 'delivery_boy')) {
            return $next($request);
        }

        return response()->json(['message' => 'You must be a delivery boy to access this page.'], 403);
    }
}
