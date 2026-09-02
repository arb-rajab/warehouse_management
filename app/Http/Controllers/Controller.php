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
     * `per_page` query param, restricted to PerPageOptions::VALUES — falls
     * back to $default when absent or not one of the allowed values.
     */
    protected function resolvePerPage(Request $request, int $default): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, PerPageOptions::VALUES, true) ? $perPage : $default;
    }
}
