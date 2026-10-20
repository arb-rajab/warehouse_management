<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends `X-Robots-Tag: noindex, nofollow` on pages that must never reach a
 * search index, whether or not anything links to them (and a header also
 * covers JSON and redirects, which carry no <meta>).
 *
 * - `noindex:always` (the store back office, the seller area and the API route
 *   groups) marks every response, including a redirect to the login page and
 *   the error a rejected caller gets.
 * - Bare `noindex` (the `web` group) marks Inertia responses only: a page that
 *   renders Inertia's root view `app`, or an Inertia visit's JSON. Those are
 *   the WMS back office and the Inertia store admin; the Blade storefront in
 *   the same group keeps its own robots rules (App\Support\Seo).
 */
class SendNoIndexHeader
{
    public const string VALUE = 'noindex, nofollow';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope = 'inertia'): Response
    {
        $response = $next($request);

        if ($scope === 'always' || $this->isInertiaResponse($response)) {
            $response->headers->set('X-Robots-Tag', self::VALUE);
        }

        return $response;
    }

    private function isInertiaResponse(Response $response): bool
    {
        if ($response->headers->has(Header::INERTIA)) {
            return true;
        }

        $view = $response instanceof IlluminateResponse ? $response->getOriginalContent() : null;

        return $view instanceof View && $view->name() === 'app';
    }
}
