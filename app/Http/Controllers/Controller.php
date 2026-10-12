<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PerPageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class Controller
{
    /**
     * Flatten a paginated resource collection into the `data` + `meta` array shape
     * Inertia pages expect, which plain serialization of the collection would drop.
     *
     * @return array<string, mixed>
     */
    protected function paginated(ResourceCollection $collection): array
    {
        return $collection->response()->getData(true);
    }

    /**
     * Redirect to a named route with the current request's query string merged
     * in, so a mutating quick-action fired from a paginated/filtered Inertia
     * index (delete, toggle, acknowledge, etc.) lands back on the same
     * page/filters instead of resetting them.
     *
     * Never use `redirect()->back()` for this: the `Referer` header is
     * stripped app-wide (see `config/secure-headers.php`) and Inertia marks
     * every visit as an XHR request, so Laravel's session middleware never
     * updates `_previous.url` during normal SPA use — see
     * `StoreInertiaPreviousUrl`'s docblock. The frontend must pair this with
     * the Wayfinder action's `{ mergeQuery: {} }` option so `$request->query()`
     * isn't empty — see .ai/rules/controllers.md.
     *
     * @param  array<string, mixed>  $routeParameters
     */
    protected function redirectPreservingQuery(string $routeName, Request $request, array $routeParameters = []): RedirectResponse
    {
        return redirect()->route($routeName, [...$routeParameters, ...$request->query()]);
    }

    /**
     * Resolve the page size for an admin table listing from the request's
     * `$key` query param (`per_page` by default — pass a distinct `$key` when
     * a page renders two independently paginated tables, e.g.
     * `reports_per_page`), restricted to PerPageOptions::VALUES — falls back
     * to $default when absent or not one of the allowed values.
     */
    protected function resolvePerPage(Request $request, int $default, string $key = 'per_page'): int
    {
        $perPage = $request->integer($key);

        return in_array($perPage, PerPageOptions::VALUES, true) ? $perPage : $default;
    }
}
