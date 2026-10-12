<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use App\Models\StoreUser;
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
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($this->isUnbannedStoreUserOfType($request, 'admin', 'staff', 'accountant')
            || ($this->isUnbannedStoreUserOfType($request, 'customer') && $user instanceof StoreUser && $user->is_rep)) {
            return $next($request);
        }

        abort(403);
    }
}
