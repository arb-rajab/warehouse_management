<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second layer of defense on top of a tool's own authorization gate: blocks
 * requests to an internal-tool route (Telescope, Pulse, ...) from IPs not in
 * the allowlist stored at the given config key. An empty list disables this
 * check (falls back to the gate alone) so an empty/unset env var can't lock
 * everyone out by accident.
 *
 * Entries may be a literal IP, a CIDR range (10.0.0.0/8, works for IPv6 too)
 * or a glob (10.0.0.*). The IP compared is $request->ip(), which reads
 * X-Forwarded-For only for proxies TRUSTED_PROXIES names in bootstrap/app.php
 * — an untrusted client cannot forge its way past this list with a header.
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
            if ($this->matchesIp(trim($allowedIp), $ip)) {
                return $next($request);
            }
        }

        abort(403);
    }

    /**
     * Decide whether an allowlist entry covers the given client IP.
     *
     * IpUtils handles literal IPs and CIDR ranges (including IPv6); the
     * Str::is() fallback keeps the documented "10.0.0.*" glob form working,
     * which IpUtils does not understand.
     */
    protected function matchesIp(string $allowedIp, ?string $ip): bool
    {
        if ($allowedIp === '' || $ip === null) {
            return false;
        }

        return IpUtils::checkIp($ip, $allowedIp) || Str::is($allowedIp, $ip);
    }
}
