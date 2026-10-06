<?php

namespace App\Http\Middleware;

use Bepsvpt\SecureHeaders\SecureHeaders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * bepsvpt/secure-headers' own middleware, plus one hook: a route middleware
 * can hand this request a different Content-Security-Policy by setting the
 * `CSP_OVERRIDE` request attribute to a map of whole CSP directives
 * (config/secure-headers.php's `csp` shape). Each directive given replaces
 * the configured one; the rest stay as configured.
 *
 * This runs outside every route middleware (it is appended globally), so it
 * writes the headers last and a route middleware cannot set the CSP header
 * itself. A request attribute, unlike a config() rewrite, ends with the
 * request: the next request in the same process starts from the strict policy.
 * HandleStoreBladePages is the one caller: it gives the store's unconverted
 * Blade pages their inline-script policy (config/store.php `blade_csp`).
 */
class SendSecureHeaders
{
    public const string CSP_OVERRIDE = 'secure-headers.csp-override';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $config = config('secure-headers', []);
        $override = $request->attributes->get(self::CSP_OVERRIDE);

        if (is_array($override)) {
            $config['csp'] = [...$config['csp'], ...$override];
        }

        foreach ((new SecureHeaders($config))->headers() as $key => $value) {
            $response->headers->set($key, $value, true);
        }

        return $response;
    }
}
