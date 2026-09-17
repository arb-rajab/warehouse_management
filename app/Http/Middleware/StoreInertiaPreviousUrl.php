<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the session's "previous URL" for Inertia page visits.
 *
 * Two settings combine to break Laravel's default `back()`/`FormRequest`
 * validation-failure redirect for every form in this app:
 *
 * - `config/secure-headers.php`'s `no-referrer` policy means the browser
 *   never sends a `Referer` header.
 * - Inertia's client sets `X-Requested-With: XMLHttpRequest` on every visit
 *   (not just partial reloads), which makes `$request->ajax()` true for
 *   ordinary page navigations too. `StartSession::storeCurrentUrl()` skips
 *   updating `session('_previous.url')` whenever `ajax()` is true, so that
 *   value is left stale at whatever URL was last *hard*-loaded (typically
 *   the dashboard after login).
 *
 * `UrlGenerator::previous()` then falls back to that stale URL (no Referer,
 * so nothing overrides it), and a `FormRequest` validation failure's default
 * `redirect()->back()` lands there instead of on the form the user submitted.
 *
 * This middleware re-implements `storeCurrentUrl()`'s own criteria but
 * substitutes an Inertia-aware check for `ajax()`: a full Inertia page visit
 * (or a genuine non-XHR page load) updates `_previous.url`; a partial reload
 * (props-only refetch of the current page, e.g. polling/pagination) does not,
 * since it isn't a navigation to a new page.
 */
class StoreInertiaPreviousUrl
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') &&
            $request->route() instanceof Route &&
            ! $request->header('X-Inertia-Partial-Data') &&
            ! $request->prefetch() &&
            ! $request->isPrecognitive()) {
            $request->session()->setPreviousUrl($request->fullUrl());
        }

        return $response;
    }
}
