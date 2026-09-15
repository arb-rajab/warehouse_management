<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowCellRequest;
use App\Http\Requests\Api\V1\ShowCellsRequest;
use App\Http\Resources\CellResource;
use App\Models\Cell;
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
                    // Inlined rather than calling Product::searchByName() from inside
                    // whereHas() on a different model — see .ai/rules/models.md.
                    fn (Builder $query) => $query->whereHas('pallet.product', function (Builder $productQuery) use ($request): void {
                        $words = preg_split('/\s+/', trim($request->string('search')->value())) ?: [];

                        foreach ($words as $word) {
                            $productQuery->where(function (Builder $matchesEitherName) use ($word): void {
                                $matchesEitherName
                                    ->where('name', 'like', '%'.$word.'%')
                                    ->orWhere('ar_name', 'like', '%'.$word.'%');
                            });
                        }
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
