<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\Request;

class IsCustomer
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
        if ($this->isUnbannedStoreUserOfType($request, 'customer')) {
            return $next($request);
        }

        session(['link' => url()->current()]);

        return redirect()->route('user.login');
    }
}
