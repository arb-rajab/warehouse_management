<?php

namespace App\Http\Controllers;

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
}
