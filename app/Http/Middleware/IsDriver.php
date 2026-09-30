<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IsDriver
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
        if (Auth::check() && Auth::user()->user_type == 'driver') {
            return $next($request);
        }
        else{
            return response()->json([
                'message' => 'You must be a driver to access this page.',
            ], 403);
        }
    }
}
