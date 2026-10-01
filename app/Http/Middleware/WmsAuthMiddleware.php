<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WmsAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $expected = (string) config('app.wms_token');

        // Fail closed: with no token configured a request that sends none would
        // otherwise compare equal to it. hash_equals() keeps the comparison
        // constant-time and strict, where `!=` was neither.
        if ($expected === '' || ! hash_equals($expected, (string) $request->header('private-token'))) {
            return response()->json([
                'result' => false,
                'message' => 'invalid token, who are you?',
                'status' => 401
            ]);
        }


        return $next($request);
    }
}
