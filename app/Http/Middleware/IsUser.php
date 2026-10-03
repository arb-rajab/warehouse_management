<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\Request;

class IsUser
{
    use RequiresUnbannedStoreUser;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->isUnbannedStoreUserOfType($request, 'customer', 'seller', 'delivery_boy')) {
            return $next($request);
        }

        session(['link' => url()->current()]);

        return redirect()->route('user.login');
    }
}
