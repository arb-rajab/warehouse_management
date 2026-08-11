<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the API's locale from the mobile client's Accept-Language header,
 * since API requests are stateless and have no session to read from.
 */
class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_map(fn (Locale $locale) => $locale->value, Locale::cases());

        app()->setLocale($request->getPreferredLanguage($supported) ?? config('app.locale'));

        return $next($request);
    }
}
