<?php

namespace App\Http\Middleware;

use App;
use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;

class AppLanguage
{
    /**
     * Handle an incoming request.
     *
     * The `App-Language` header is client-supplied, so it only counts when it
     * names a locale the app has (App\Enums\Locale); anything else falls back
     * to the configured default, the same as no header.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $requested = (string) $request->header('App-Language');

        if (in_array($requested, array_column(Locale::cases(), 'value'), true)) {
            $locale = $requested;
        } else {
            $locale = config('app.default_language') ?? 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
