<?php

namespace App\Http\Middleware;

use App\Models\MobileAppVersionRequirement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects mobile API requests from a client build older than the minimum set
 * via `php artisan app:set-minimum-app-version`. No minimum configured yet
 * means no restriction — every request passes through unchanged.
 */
class EnsureMinimumAppVersion
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $minimumVersion = MobileAppVersionRequirement::minimumVersion();

        if ($minimumVersion === null) {
            return $next($request);
        }

        $clientVersion = $request->header('X-App-Version');

        if (! is_string($clientVersion) || version_compare($clientVersion, $minimumVersion, '<')) {
            return response()->json([
                'message' => __('messages.app_version_outdated'),
                'error_code' => 'app_version_outdated',
                'minimum_version' => $minimumVersion,
            ], Response::HTTP_UPGRADE_REQUIRED);
        }

        return $next($request);
    }
}
