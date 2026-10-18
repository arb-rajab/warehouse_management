<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellQrLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterRowsRequest;
use App\Http\Requests\Admin\ShowRowRequest;
use App\Http\Requests\Admin\StoreRowRequest;
use App\Http\Requests\Admin\UpdateRowRequest;
use App\Http\Resources\CellResource;
use App\Http\Resources\RowResource;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RowController extends Controller
{
    use BuildsCellQrLabels;

    public function index(FilterRowsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Admin/Rows/Index', [
            'rows' => $this->paginated(RowResource::collection(
                Row::query()
                    ->select(Row::SELECT_COLUMNS)
                    ->withHasPallets()
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Rows/Create');
    }

    public function store(StoreRowRequest $request): RedirectResponse
    {
        $row = DB::transaction(fn () => Row::create($request->validated()));

        return redirect()->route('admin.rows.show', $row);
    }

    public function show(ShowRowRequest $request, Row $row): Response
    {
        $cellNumber = $request->integer('cell_number') ?: null;
        $flatNumber = $request->integer('flat_number') ?: null;

        return Inertia::render('Admin/Rows/Show', [
            'row' => new RowResource($row->loadHasPallets()),
            'rows' => Row::query()->select(Row::SELECT_COLUMNS)->orderBy('letter')->get(),
            'cells' => CellResource::collection(
                $row->cells()
                    ->select(Cell::SELECT_COLUMNS)
                    ->with(Cell::WITH_CONTENTS)
                    ->orderedByCoordinates()
                    ->get()
            ),
            'today' => today()->toDateString(),
            'filterOptions' => [
                'products' => Product::selectedOptions(),
            ],
            'linkedCell' => $cellNumber !== null && $flatNumber !== null ? [
                'row_letter' => $row->letter,
                'cell_number' => $cellNumber,
                'flat_number' => $flatNumber,
            ] : null,
        ]);
    }

    public function edit(Row $row): Response
    {
        return Inertia::render('Admin/Rows/Edit', [
            'row' => new RowResource($row->loadHasPallets()),
        ]);
    }

    public function exportQrCodes(Row $row): HttpResponse
    {
        // cells_count/flats_count can each be as large as Row::MAX_DIMENSION, so a
        // maximally-sized row could still take a while to render even with
        // SVG-rendered QRs (see BuildsCellQrLabels) — buy headroom beyond PHP's
        // default 30s limit.
        set_time_limit(300);

        $cells = $row->cells()
            ->select(['id', 'cell_number', 'flat_number'])
            ->orderedByCoordinates()
            ->get();
        $setting = Setting::current();

        $pdf = Pdf::loadView('pdf.qr-labels', [
            'labels' => $this->cellQrLabels($row->letter, $cells, $setting->qr_code_width, $setting->qr_code_height, $row->flats_count),
            'qrWidth' => $setting->qr_code_width,
            'qrHeight' => $setting->qr_code_height,
        ]);

        // php-svg-lib selects core fonts by basename. Load bold Helvetica's
        // metrics into dompdf's cache before SVG text asks for that basename;
        // on a fresh installation it otherwise falls back to regular Times.
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $fontMetrics->getTextWidth(' ', $fontMetrics->getFont('sans-serif', 'bold'), 1);

        return $pdf->download("row-{$row->letter}-qr-codes.pdf");
    }

    public function update(UpdateRowRequest $request, Row $row): RedirectResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $row) {
            $shrinksColumns = $validated['cells_count'] < $row->cells_count;
            $shrinksLevels = $validated['flats_count'] < $row->flats_count;

            if ($shrinksColumns || $shrinksLevels) {
                $row->cells()->lockForUpdate()->get();

                $errors = [];

                foreach ([
                    'cells_count' => $shrinksColumns ? [$validated['cells_count'], null, 'columns'] : null,
                    'flats_count' => $shrinksLevels ? [null, $validated['flats_count'], 'levels'] : null,
                ] as $field => $removal) {
                    if ($removal === null) {
                        continue;
                    }

                    [$cellsBound, $flatsBound, $axis] = $removal;

                    if ($row->hasPalletsBeyond($cellsBound, $flatsBound)) {
                        $errors[$field] = __("messages.row_cannot_reduce_{$axis}_has_pallets");
                    } elseif ($row->hasHistoryBeyond($cellsBound, $flatsBound)) {
                        $errors[$field] = __("messages.row_cannot_reduce_{$axis}_has_history");
                    }
                }

                if ($errors !== []) {
                    return redirect()->route('admin.rows.edit', $row)->withErrors($errors);
                }
            }

            $row->update($validated);

            return redirect()->route('admin.rows.show', $row);
        });
    }

    public function destroy(Request $request, Row $row): RedirectResponse
    {
        return DB::transaction(function () use ($request, $row) {
            $blockReason = $this->lockedRowBlockReason(
                $row,
                'messages.row_cannot_delete_has_pallets',
                'messages.row_cannot_delete_has_history',
            );

            if ($blockReason !== null) {
                return $this->redirectPreservingQuery('admin.rows.index', $request)->withErrors(['row' => $blockReason]);
            }

            $row->delete();

            return $this->redirectPreservingQuery('admin.rows.index', $request);
        });
    }

    /**
     * Locks the row's cells for the remainder of the transaction, then
     * returns the translated block message when the row currently has
     * pallets or has history (past status logs/verification reports) that
     * would otherwise fail on the `restrictOnDelete` constraints once the
     * cells are deleted/regenerated — null when neither blocks the action.
     */
    private function lockedRowBlockReason(Row $row, string $palletsMessageKey, string $historyMessageKey): ?string
    {
        $row->cells()->lockForUpdate()->get();

        if ($row->hasPallets()) {
            return __($palletsMessageKey);
        }

        if ($row->hasHistory()) {
            return __($historyMessageKey);
        }

        return null;
    }
}
