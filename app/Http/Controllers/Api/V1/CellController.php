<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CellResource;
use App\Models\Cell;
use App\Models\Row;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CellController extends Controller
{
    public function index(Row $row): AnonymousResourceCollection
    {
        return CellResource::collection(
            $row->cells()
                ->select(['id', 'row_id', 'cell_number', 'flat_number', 'state'])
                ->with(Cell::WITH_ROW_AND_CONTENTS)
                ->orderedByCoordinates()
                ->paginate(20)
        );
    }

    public function show(Row $row, string $cellNumber, string $flatNumber): CellResource
    {
        $cell = Cell::query()
            ->select(['id', 'row_id', 'cell_number', 'flat_number', 'state'])
            ->atCoordinates($row, (int) $cellNumber, (int) $flatNumber)
            ->with(Cell::WITH_ROW_AND_CONTENTS)
            ->first();

        abort_if($cell === null, 404);

        return new CellResource($cell);
    }
}
