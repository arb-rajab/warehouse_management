<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the admin panel's locale from the session (set by LocaleController),
 * falling back to the app default.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::tryFrom((string) $request->session()->get('locale'))->value
            ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }
}
