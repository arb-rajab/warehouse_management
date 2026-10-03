<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IsRepresentativeOrAdmin
{
    use RequiresUnbannedStoreUser;

    /**
     * Let through an unbanned admin, staff or accountant, or a sales
     * representative (a customer account with `is_rep` set).
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->isUnbannedStoreUserOfType($request, 'admin', 'staff', 'accountant')
            || ($this->isUnbannedStoreUserOfType($request, 'customer') && $request->user()->is_rep)) {
            return $next($request);
        }

        abort(403);
    }
}
