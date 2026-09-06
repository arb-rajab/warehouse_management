<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowRowsFullRequest;
use App\Http\Resources\RowResource;
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
                ->withExists(['cells as has_pallets' => fn ($query) => $query->has('pallet')])
                ->paginate(20)
        );
    }

    /**
     * Every row with its cells and the pallet occupying each occupied flat, unpaginated.
     */
    public function full(ShowRowsFullRequest $request): AnonymousResourceCollection
    {
        return RowResource::collection(
            Row::query()
                ->select(Row::SELECT_COLUMNS)
                ->with(['cells' => fn ($query) => $query
                    ->select(Cell::SELECT_COLUMNS)
                    ->with(Cell::WITH_CONTENTS)
                    ->orderedByCoordinates()])
                ->orderBy('letter')
                ->get()
        );
    }
}
