<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UseStoreAuthGuard
{
    /**
     * Handle an incoming request.
     *
     * Every legacy store auth entry point — LoginController/
     * RegisterController/ResetPasswordController (via their Laravel-UI
     * traits' `guard()` methods) and VerificationController plus every
     * custom role-gate middleware (IsAdmin, IsUser, IsCustomer, IsSeller) —
     * calls the bare `Auth::check()`/`Auth::user()`/`$this->guard()`, none
     * of which name a guard explicitly. Before this middleware, all of them
     * resolved to `config('auth.defaults.guard')` ('web', provider
     * App\Models\User — this app's own admin model), so no StoreUser could
     * ever log in, register, or pass a single one of those role checks: the
     * 'store' guard (provider StoreUser, see config/auth.php) existed but
     * nothing under /temp actually used it.
     *
     * `Auth::shouldUse('store')` changes what the *bare* `Auth::guard()`/
     * `$request->user()` resolve to for the rest of this request — it does
     * not touch any explicitly-named guard (`Auth::guard('web')`,
     * `auth:sanctum`, `wms.auth`), so WMS's own admin panel and the
     * warehouse API are unaffected. Placed first in the shop-web/shop-admin/
     * shop-seller middleware group in RouteServiceProvider::map(), before
     * any route-level auth/role middleware runs.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        Auth::shouldUse('store');

        return $next($request);
    }
}
