<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second layer of defense on top of a tool's own authorization gate: blocks
 * requests to an internal-tool route (Telescope, Pulse, ...) from IPs not in
 * the allowlist stored at the given config key. An empty list disables this
 * check (falls back to the gate alone) so an empty/unset env var can't lock
 * everyone out by accident.
 */
class RestrictToAllowedIps
{
    public function handle(Request $request, Closure $next, string $configKey): Response
    {
        $allowedIps = array_filter(explode(',', (string) config($configKey)));

        if ($allowedIps === []) {
            return $next($request);
        }

        $ip = $request->ip();

        foreach ($allowedIps as $allowedIp) {
            if (Str::is(trim($allowedIp), $ip)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
