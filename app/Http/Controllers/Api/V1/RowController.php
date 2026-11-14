<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterRowsFullRequest;
use App\Http\Requests\Api\V1\FilterRowsRequest;
use App\Http\Resources\RowResource;
use App\Http\Resources\RowSummaryResource;
use App\Models\Row;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RowController extends Controller
{
    /**
     * The rows, 20 per page; `warehouse_ids[]` narrows them to those warehouses.
     */
    public function index(FilterRowsRequest $request): AnonymousResourceCollection
    {
        return RowResource::collection(
            Row::query()
                ->select(Row::SELECT_COLUMNS)
                ->with(Row::WITH_WAREHOUSE)
                ->withHasPallets()
                ->forWarehouses($request->warehouseIds())
                ->paginate(20)
                ->withQueryString()
        );
    }

    /**
     * Every row with its cells and the pallet occupying each occupied flat, unpaginated.
     * `product_status` narrows each row's `cells` to only those occupied by a
     * matching product, dropping empty cells and non-matching pallets from
     * the returned grid. `warehouse_ids[]` narrows the rows to those warehouses.
     */
    public function full(FilterRowsFullRequest $request): AnonymousResourceCollection
    {
        return RowResource::collection(
            Row::query()->withFullGrid($request->productPublished())->forWarehouses($request->warehouseIds())->get()
        );
    }

    /**
     * Every row any unfinished verification round currently claims, regardless
     * of which user owns that round. `GET /cell-verification-rounds?only_unfinished=true`
     * only surfaces the caller's own open round, so it can't tell the mobile
     * app a row is frozen because a *different* worker is walking it — this is
     * what lets the app show that proactive "frozen" state before it even
     * attempts a pallet action there, rather than only after the server
     * refuses it. `warehouse_ids[]` narrows the rows to those warehouses.
     */
    public function frozen(FilterRowsRequest $request): AnonymousResourceCollection
    {
        return RowSummaryResource::collection(
            Row::query()
                ->select(['id', 'warehouse_id', 'letter'])
                ->with(Row::WITH_WAREHOUSE)
                ->underActiveVerification()
                ->forWarehouses($request->warehouseIds())
                ->orderBy('letter')
                ->get()
        );
    }
}
