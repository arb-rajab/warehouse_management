<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowCellRequest;
use App\Http\Requests\Api\V1\ShowCellsRequest;
use App\Http\Resources\CellResource;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CellController extends Controller
{
    public function index(ShowCellsRequest $request, Row $row): AnonymousResourceCollection
    {
        return CellResource::collection(
            $row->cells()
                ->select(Cell::SELECT_COLUMNS)
                ->with(Cell::WITH_ROW_AND_CONTENTS)
                ->when(
                    $request->filled('search'),
                    // Product::applyNameSearch() takes the underlying query builder
                    // rather than this Eloquent one, because calling that model's
                    // #[Scope] from inside whereHas() on a different model loses its
                    // generic type under Larastan — see .ai/rules/models.md. That is
                    // what lets this share one implementation with the scope instead
                    // of inlining a second copy that would drift from it.
                    fn (Builder $query) => $query->whereHas('pallet.product', function (Builder $productQuery) use ($request): void {
                        Product::applyNameSearch($productQuery->getQuery(), $request->string('search')->value());
                    })
                )
                ->orderedByCoordinates()
                ->paginate(20)
        );
    }

    public function show(ShowCellRequest $request, Row $row, string $cellNumber, string $flatNumber): CellResource
    {
        $cell = Cell::query()
            ->select(Cell::SELECT_COLUMNS)
            ->atCoordinates($row, (int) $cellNumber, (int) $flatNumber)
            ->with(Cell::WITH_ROW_AND_CONTENTS)
            ->first();

        abort_if($cell === null, 404);

        return new CellResource($cell);
    }
}
