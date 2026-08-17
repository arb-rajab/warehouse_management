<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowCellMapRequest;
use App\Http\Resources\CellResource;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Row;
use Inertia\Inertia;
use Inertia\Response;

class CellController extends Controller
{
    public function index(ShowCellMapRequest $request): Response
    {
        $rows = Row::query()->select(['id', 'letter', 'cells_count', 'flats_count'])->orderBy('letter')->get();
        $maxFlatNumber = (int) (Row::query()->max('flats_count') ?? 0);

        $today = today();

        $searched = $request->filled('search');
        $matchedCell = $searched ? $this->resolveLocationSearch($request->string('search')->value()) : null;

        $flatNumber = $matchedCell !== null
            ? $matchedCell->flat_number
            : min(max($request->integer('flat_number', 1), 1), max($maxFlatNumber, 1));

        $cells = Cell::query()
            ->select(['id', 'row_id', 'cell_number', 'flat_number', 'state'])
            ->where('flat_number', $flatNumber)
            ->with(Cell::WITH_ROW_AND_CONTENTS)
            ->orderedByCoordinates()
            ->get();

        return Inertia::render('Admin/Cells/Index', [
            'rows' => $rows,
            'cells' => CellResource::collection($cells),
            'flatNumber' => $flatNumber,
            'maxFlatNumber' => $maxFlatNumber,
            'today' => $today->toDateString(),
            'initialHighlight' => [
                'state' => $request->string('state')->value() ?: null,
                'productIds' => $request->productIds() ?? [],
                'expiresWithinDays' => $request->filled('expires_within_days')
                    ? $request->integer('expires_within_days')
                    : null,
                'expired' => $request->boolean('expired'),
            ],
            'jumpToCell' => $matchedCell?->toLocationArray(),
            'searchError' => $searched && $matchedCell === null,
            'filterOptions' => [
                'products' => Product::filterOptions(),
            ],
        ]);
    }

    /**
     * Parses a row-letter + cell-number (+ optional flat-number) location, matching the
     * "A12·3" shape rendered on every cell slot — e.g. "A12", "A12·3", "A12-3".
     */
    private function resolveLocationSearch(string $search): ?Cell
    {
        if (! preg_match('/^\s*([A-Za-z]+)\s*(\d+)(?:[^0-9A-Za-z]+(\d+))?\s*$/u', $search, $matches)) {
            return null;
        }

        $row = Row::query()->where('letter', mb_strtoupper($matches[1]))->first();

        if ($row === null) {
            return null;
        }

        $cellNumber = (int) $matches[2];

        if (isset($matches[3])) {
            return Cell::query()->atCoordinates($row, $cellNumber, (int) $matches[3])->with('row:id,letter')->first();
        }

        return Cell::query()
            ->where('row_id', $row->id)
            ->where('cell_number', $cellNumber)
            ->with('row:id,letter')
            ->orderedByCoordinates()
            ->first();
    }
}
