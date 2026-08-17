<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal application-level web application firewall: blocks any request
 * whose URI or query/body input matches a known attack signature (SQL
 * injection, XSS, path traversal, command injection) from config/waf.php,
 * before it ever reaches routing or a controller.
 */
class BlockMaliciousRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('waf.enabled')) {
            return $next($request);
        }

        foreach (config('waf.exclude_paths', []) as $excludedPath) {
            if ($request->is($excludedPath)) {
                return $next($request);
            }
        }

        $subject = $request->getRequestUri().' '.(json_encode($request->all()) ?: '');

        foreach (config('waf.patterns', []) as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $subject) === 1) {
                    Log::warning('Blocked malicious request', [
                        'ip' => $request->ip(),
                        'category' => $category,
                        'uri' => $request->getRequestUri(),
                    ]);

                    abort(403);
                }
            }
        }

        return $next($request);
    }
}
