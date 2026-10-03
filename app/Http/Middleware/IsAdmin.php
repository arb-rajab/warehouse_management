<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\Request;

class IsAdmin
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
        if ($this->isUnbannedStoreUserOfType($request, 'admin', 'staff', 'accountant')) {
            return $next($request);
        }

        abort(404);
    }
}
