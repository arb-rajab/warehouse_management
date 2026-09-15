<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowRowsFullRequest;
use App\Http\Resources\RowResource;
use App\Http\Resources\RowSummaryResource;
use App\Models\Cell;
use App\Models\Row;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RowController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RowResource::collection(
            Row::query()
                ->select(Row::SELECT_COLUMNS)
                ->withHasPallets()
                ->paginate(20)
        );
    }

    /**
     * Every row with its cells and the pallet occupying each occupied flat, unpaginated.
     * `product_status` narrows each row's `cells` to only those occupied by a
     * matching product, dropping empty cells and non-matching pallets from
     * the returned grid.
     */
    public function full(ShowRowsFullRequest $request): AnonymousResourceCollection
    {
        $productPublished = $request->productPublished();

        return RowResource::collection(
            Row::query()
                ->select(Row::SELECT_COLUMNS)
                ->withHasPallets()
                ->with(['cells' => fn ($query) => $query
                    ->select(Cell::SELECT_COLUMNS)
                    ->with(Cell::WITH_CONTENTS)
                    ->when($productPublished !== null, fn ($q) => $q->whereHas('pallet.product', fn ($q2) => $q2->where('published', $productPublished)))
                    ->orderedByCoordinates()])
                ->orderBy('letter')
                ->get()
        );
    }

    /**
     * Every row any unfinished verification round currently claims, regardless
     * of which user owns that round. `GET /cell-verification-rounds?only_unfinished=true`
     * only surfaces the caller's own open round, so it can't tell the mobile
     * app a row is frozen because a *different* worker is walking it — this is
     * what lets the app show that proactive "frozen" state before it even
     * attempts a pallet action there, rather than only after the server
     * refuses it.
     */
    public function frozen(): AnonymousResourceCollection
    {
        return RowSummaryResource::collection(
            Row::query()
                ->select(['id', 'letter'])
                ->underActiveVerification()
                ->orderBy('letter')
                ->get()
        );
    }
}
