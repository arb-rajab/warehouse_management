<?php

namespace App\Http\Middleware;

use App\Support\StoreLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppLanguage
{
    /** App-Language takes precedence over Accept-Language on the store API. */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->header('App-Language');
        $explicit = in_array($requested, ['en', 'ar', 'sa'], true);

        return StoreLocale::withLocale(
            $explicit ? $requested : (config('app.default_language') ?? 'en'),
            StoreLocale::deliveryLocale(locale: $explicit ? $requested : null),
            fn () => $next($request),
        );
    }
}
