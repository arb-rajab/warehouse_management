<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCellsRequest;
use App\Http\Resources\CellResource;
use App\Models\Cell;
use App\Models\Row;
use Inertia\Inertia;
use Inertia\Response;

class CellController extends Controller
{
    public function index(FilterCellsRequest $request): Response
    {
        $cells = Cell::query()
            ->select(['id', 'row_id', 'cell_number', 'flat_number', 'state'])
            ->with(Cell::WITH_ROW_AND_CONTENTS)
            ->filtered($request)
            ->sorted($request)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Cells/Index', [
            'cells' => $this->paginated(CellResource::collection($cells)),
            'filters' => $request->only(['state', 'row_id', 'column_number', 'expiration_date_from', 'expiration_date_to', 'sort_by', 'sort_direction']),
            'filterOptions' => [
                ...Row::filterOptions(),
                'states' => array_column(CellState::cases(), 'value'),
            ],
        ]);
    }
}
