<?php

namespace App\Http\Middleware;

use App\Support\StoreLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
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
        $locale = StoreLocale::deliveryLocale();
        // Symfony coerces malformed quality weights; validate before negotiating.
        $choices = array_filter(
            explode(',', (string) $request->header('Accept-Language')),
            fn (string $choice) => preg_match('/^\s*(en|ar|sa)(?:[-_][a-z0-9]{2,8})*(?:\s*;\s*q=(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?))?\s*$/iD', $choice),
        );

        foreach (AcceptHeader::fromString(implode(',', $choices))->all() as $language) {
            if ($language->getQuality() > 0
                && preg_match('/^(en|ar|sa)(?:[-_][a-z0-9]{2,8})*$/iD', $language->getValue(), $matches)) {
                $locale = StoreLocale::uiLocale(strtolower($matches[1]));
                break;
            }
        }

        return StoreLocale::withLocale($locale, $locale, fn () => $next($request));
    }
}
