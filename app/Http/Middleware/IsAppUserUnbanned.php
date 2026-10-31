<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;

class IsAppUserUnbanned
{
    /**
     * Refuses a banned user with a 403 and revokes the token they used.
     *
     * A request with no user passes through untouched: authenticating is
     * `auth:store_api`'s job, which every route behind this middleware also
     * carries. The user and the token were dereferenced unguarded, so a
     * request that reached here without either was a 500, and a banned user
     * got a 200 whose body said `result: false`.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null || ! $user->banned) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'result' => false,
            'result_key' => 'banned',
            'message' => __('storefront.common.you_are_banned'),
        ], 403);
    }
}
