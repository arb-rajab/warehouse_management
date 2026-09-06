<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PerPageOptions;
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
