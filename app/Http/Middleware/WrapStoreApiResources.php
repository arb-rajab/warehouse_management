<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the legacy store API's resources with the `data` wrapper they were
 * written for.
 *
 * AppServiceProvider turns resource wrapping off for this app's own API. The
 * store's resource collections return `['data' => ...]` from toArray(), plus
 * `success` and `status` from with(), and with no wrapper Laravel nests all
 * of that under a second `data` key: `{"data":{"data":[...]}}`, which the
 * store's mobile app doesn't read. With the wrapper back, Laravel takes the
 * existing key for it and leaves the payload as the app expects:
 * `{"data":[...],"success":true,"status":200}`.
 *
 * The wrapper is one static shared by every resource, so it is put back as
 * it was once the response has been built.
 */
class WrapStoreApiResources
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previousWrapper = JsonResource::$wrap;

        JsonResource::wrap('data');

        try {
            return $next($request);
        } finally {
            JsonResource::$wrap = $previousWrapper;
        }
    }
}
