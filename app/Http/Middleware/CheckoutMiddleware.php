<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use Auth;
use Closure;
use Illuminate\Http\Request;

class CheckoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (BusinessSetting::where('type', 'guest_checkout_active')->first()?->value != 1) {
            if(Auth::check()){
                return $next($request);
            }
            else {
                session(['link' => url()->current()]);
                return redirect()->route('user.login');
            }
        }
        else{
            return $next($request);
        }
    }
}
