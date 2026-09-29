<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WmsAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
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
