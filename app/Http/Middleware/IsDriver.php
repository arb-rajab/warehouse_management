<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RequiresUnbannedStoreUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IsDriver
{
    use RequiresUnbannedStoreUser;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->isUnbannedStoreUserOfType($request, 'driver')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must be a driver to access this page.',
        ], 403);
    }
}
