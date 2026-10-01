<?php

namespace App\Http\Middleware;

use App\Models\StoreUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The delivery-boy app's routes: a signed-in delivery boy only. They were open
 * to any signed-in account, which could read a delivery boy's earnings and
 * collections, mark any order delivered (paid, for cash on delivery, with a
 * commission credited every time) or put in a cancellation request.
 */
class IsDeliveryBoy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof StoreUser && $user->user_type === 'delivery_boy') {
            return $next($request);
        }

        return response()->json(['message' => 'You must be a delivery boy to access this page.'], 403);
    }
}
