<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the store's Inertia pages and its unconverted Blade pages share /temp
 * while the back office moves to Inertia one module at a time
 * (.ai/rules/store-backoffice-migration.md §2.6). A response is a Blade page
 * when it renders a view other than Inertia's root view, which is the only
 * view named `app`. A CMS or policy Blade view can also carry `page` data.
 *
 * - An Inertia visit that lands on a Blade page (a link the menu still marks
 *   as Inertia, or a converted form whose redirect goes to an unconverted
 *   list) gets a 409 + X-Inertia-Location, so the browser loads the page
 *   instead of Inertia showing raw HTML in its error modal.
 * - A Blade page gets the store's inline-script policy (config/store.php
 *   `blade_csp`) through SendSecureHeaders. Inertia pages and everything else
 *   keep config/secure-headers.php's strict one.
 */
class HandleStoreBladePages
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isBladePage($response)) {
            return $response;
        }

        if ($request->header(Header::INERTIA) && $request->isMethod('GET')) {
            return Inertia::location($request->fullUrl());
        }

        $request->attributes->set(SendSecureHeaders::CSP_OVERRIDE, config('store.blade_csp'));

        return $response;
    }

    private function isBladePage(Response $response): bool
    {
        $view = $response instanceof IlluminateResponse ? $response->getOriginalContent() : null;

        return $view instanceof View && $view->name() !== 'app';
    }
}
