<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterWarehouseMapRequest;
use App\Http\Resources\RowResource;
use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseMapController extends Controller
{
    /**
     * The 2D / 3D warehouse map page. It carries no row data of its own: the
     * page fetches {@see self::rows()} so that it can revalidate it (ETag)
     * whenever the tab regains focus, without reloading the Inertia page. It
     * does carry the warehouse filter (`warehouse_ids[]`) and the selected
     * warehouses' labels, the same shape as the dashboard's.
     */
    public function index(FilterWarehouseMapRequest $request): Response
    {
        $warehouseIds = $request->warehouseIds();

        return Inertia::render('Admin/WarehouseMap/Index', [
            'filters' => [
                'warehouse_ids' => $warehouseIds,
            ],
            'filterOptions' => [
                'warehouses' => Warehouse::selectedOptions($warehouseIds ?? []),
            ],
        ]);
    }

    /**
     * The whole warehouse grid, in exactly the shape of the mobile app's
     * `GET /api/v1/rows/full`, for the session-authenticated panel (the
     * mobile endpoint only accepts Sanctum tokens), narrowed by the same
     * optional `warehouse_ids[]`. The route adds an ETag.
     */
    public function rows(FilterWarehouseMapRequest $request): AnonymousResourceCollection
    {
        return RowResource::collection(Row::query()->withFullGrid()->forWarehouses($request->warehouseIds())->get());
    }
}
