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

        $token = $request->header('private-token');

        if ($token != config('app.wms_token')) {
            return response()->json([
                'result' => false,
                'message' => 'invalid token, who are you?',
                'status' => 401
            ]);
        }


        return $next($request);
    }
}
