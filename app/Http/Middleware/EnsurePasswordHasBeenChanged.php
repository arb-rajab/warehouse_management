<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a signed-in user whose `must_change_password` flag is still set
 * (a fresh or admin-reset account, see Admin\UserController) to the
 * change-password page before reaching anything else — except the page
 * itself, its submit route, logging out, and switching the UI language,
 * which all need to stay reachable or the user could never clear the flag
 * or leave the flow.
 */
class EnsurePasswordHasBeenChanged
{
    /**
     * Route names that stay reachable while a password change is pending.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = ['password.change', 'password.update', 'logout', 'locale.update'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
