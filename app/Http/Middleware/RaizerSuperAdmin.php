<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class RaizerSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * Anyone but the vendor's own super admin is refused with a 403. The
     * legacy version fell off the end instead, returning null, which Laravel
     * 8 turned into an empty 200 page; this app's own web middleware
     * (EnsurePasswordHasBeenChanged) declares a Response return type, so
     * that became a 500.
     *
     * @param  Request  $request
     * @param  \Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ((Auth::check()) && (Auth::user()->user_type == 'admin') && (Auth::user()->raizer_admin == true)) {
            return $next($request);
        }

        abort(403);
    }
}
