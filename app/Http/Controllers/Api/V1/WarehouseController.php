<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    /**
     * Every warehouse, by name, unpaginated (a WMS has a handful): the
     * options of the app's warehouse filter (`warehouse_ids[]` on the
     * dashboard, rows, cell logs and verification rounds) and the
     * `warehouse_id` that picks a row when a letter is used in more than one.
     */
    public function index(): AnonymousResourceCollection
    {
        return WarehouseResource::collection(
            Warehouse::query()->select(['id', 'name', 'code', 'address'])->orderBy('name')->get()
        );
    }
}
