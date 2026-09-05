<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellLogAction;
use App\Http\Controllers\Concerns\BuildsCellQrLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowCellMapRequest;
use App\Http\Requests\Admin\ToggleCellActiveRequest;
use App\Http\Resources\CellResource;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Product;
use App\Models\Row;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CellController extends Controller
{
    use BuildsCellQrLabels;

    public function index(ShowCellMapRequest $request): Response
    {
        $rows = Row::mapOptions();
        $maxFlatNumber = (int) (Row::query()->max('flats_count') ?? 0);

        $today = today();

        $searched = $request->filled('search');
        $matchedCell = $searched ? $this->resolveLocationSearch($request->string('search')->value()) : null;

        $flatNumber = $matchedCell !== null
            ? $matchedCell->flat_number
            : min(max($request->integer('flat_number', 1), 1), max($maxFlatNumber, 1));

        $cells = Cell::query()
            ->select(Cell::SELECT_COLUMNS)
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
                'inactive' => $request->filled('is_active') && ! $request->boolean('is_active'),
            ],
            'jumpToCell' => $matchedCell?->toLocationArray(),
            'searchError' => $searched && $matchedCell === null,
            'filterOptions' => [
                'products' => Product::selectedOptions($request->productIds() ?? []),
            ],
            'cellHighlightSamples' => $this->cellHighlightSamples(),
        ]);
    }

    public function exportQr(Cell $cell): HttpResponse
    {
        $cell->loadMissing('row:id,letter');

        return Pdf::loadView('pdf.cell-qr-labels', [
            'labels' => $this->cellQrLabels($cell->row->letter, collect([$cell])),
        ])->download("cell-{$cell->row->letter}{$cell->cell_number}-{$cell->flat_number}-qr-code.pdf");
    }

    /**
     * Toggle a cell's active status — deactivating flags it as corrupted/out of
     * service (regardless of its current occupancy), reactivating restores it.
     * Occupancy (`state`) is never touched by this action.
     *
     * Triggered from both the cell map and a row's page, so — like
     * `PalletController::handle()` — the redirect target comes from an explicit
     * `return_to` field the frontend sends (validated by `ValidatesReturnTo`),
     * not `back()`: Inertia marks every visit as an XHR request, which stops
     * Laravel's session middleware from ever updating the tracked "previous URL"
     * `back()` relies on during normal SPA navigation, and the `Referer` header
     * is stripped app-wide by `config/secure-headers.php`'s `Referrer-Policy:
     * no-referrer` besides.
     */
    public function toggleActive(ToggleCellActiveRequest $request, Cell $cell): RedirectResponse
    {
        return DB::transaction(function () use ($request, $cell) {
            /** @var Cell $lockedCell */
            $lockedCell = Cell::query()->with('pallet:id,cell_id,product_id')->lockForUpdate()->findOrFail($cell->id);

            $activating = ! $lockedCell->is_active;
            $lockedCell->update(['is_active' => $activating]);

            CellStatusLog::create([
                'cell_id' => $lockedCell->id,
                'related_cell_id' => null,
                'action' => $activating ? CellLogAction::Reactivated : CellLogAction::Deactivated,
                'from_state' => $lockedCell->state,
                'to_state' => $lockedCell->state,
                'product_id' => $lockedCell->pallet?->product_id,
                'pallet_id' => $lockedCell->pallet?->id,
                'boxes_count' => null,
                'user_id' => $request->user()->id,
                'note' => $request->input('note'),
            ]);

            return $this->redirectAfterToggle($request, $lockedCell);
        });
    }

    private function redirectAfterToggle(Request $request, Cell $cell): RedirectResponse
    {
        if ($request->input('return_to') === 'row') {
            return redirect()->route('admin.rows.show', $cell->loadMissing('row:id,letter')->row->letter);
        }

        return redirect()->route('admin.cells.index', $request->query());
    }

    /**
     * The minimal per-cell data needed to compute a highlight-match count for
     * every flat (not just the one currently on screen) — the map only ever
     * loads one flat's full cell/row/pallet.product data at a time, so this
     * covers the rest with the fewest columns that `matchesCellHighlight()`
     * on the frontend needs. `row_letter`/`cell_number` are included so the
     * frontend can also order matches for next/previous-match navigation.
     * `cell_id`/`pallet.id`/`pallet.remaining_boxes` are included so the 3D
     * map's faced-cell panel (built from this same data — see `map3DBands` in
     * Cells/Index.vue) can drive real pallet actions/toggle-active, not just
     * display detail.
     *
     * @return array<int, array{cell_id: int, row_letter: string, cell_number: int, flat_number: int, state: 'empty'|'full'|'opened', is_active: bool, pallet: array{id: int, product_id: int, product_name: string, product_image_url: string|null, expiration_date: string, added_at: string|null, remaining_boxes: int}|null}>
     */
    private function cellHighlightSamples(): array
    {
        return Cell::query()
            ->select(Cell::SELECT_COLUMNS)
            ->with(Cell::WITH_ROW_AND_CONTENTS)
            ->orderedByCoordinates()
            ->get()
            ->map(fn (Cell $cell) => [
                'cell_id' => $cell->id,
                'row_letter' => $cell->row->letter,
                'cell_number' => $cell->cell_number,
                'flat_number' => $cell->flat_number,
                'state' => $cell->state->value,
                'is_active' => $cell->is_active,
                'pallet' => $cell->pallet === null ? null : [
                    'id' => $cell->pallet->id,
                    ...$cell->pallet->toMapSummaryArray(),
                    'remaining_boxes' => $cell->pallet->remaining_boxes,
                ],
            ])
            ->all();
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
