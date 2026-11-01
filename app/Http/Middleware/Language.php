<?php

namespace App\Http\Middleware;

use App\Support\StoreLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Language
{
    /**
     * The explicit session choice wins, then the storefront toggle's long-lived
     * cookie, then APP_LOCALE. Neither fallback is written back to the session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! StoreLocale::isSupported($locale)) {
            $locale = $request->cookie(StoreLocale::COOKIE);
        }

        app()->setLocale(StoreLocale::isSupported($locale) ? $locale : config('app.locale'));
        app('translator')->setLocale(StoreLocale::uiLocale());
        app('translator')->setFallback(StoreLocale::uiLocale(config('app.fallback_locale')));

        return $next($request);
    }
}
