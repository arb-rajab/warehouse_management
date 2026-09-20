<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellQrLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRowRequest;
use App\Http\Requests\UpdateRowRequest;
use App\Http\Resources\CellResource;
use App\Http\Resources\RowResource;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Row;
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

    public function index(Request $request): Response
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
        $row = Row::create($request->validated());

        return redirect()->route('admin.rows.show', $row);
    }

    public function show(Row $row): Response
    {
        return Inertia::render('Admin/Rows/Show', [
            'row' => new RowResource($row->loadHasPallets()),
            'rows' => Row::mapOptions(),
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
        // No upper bound on cells_count/flats_count is enforced, so an unusually
        // large row could still take a while to render even with SVG-rendered QRs
        // (see BuildsCellQrLabels) — buy headroom beyond PHP's default 30s limit.
        set_time_limit(300);

        $cells = $row->cells()
            ->select(['id', 'cell_number', 'flat_number'])
            ->orderedByCoordinates()
            ->get();

        return Pdf::loadView('pdf.qr-labels', [
            'labels' => $this->cellQrLabels($row->letter, $cells),
        ])->download("row-{$row->letter}-qr-codes.pdf");
    }

    public function update(UpdateRowRequest $request, Row $row): RedirectResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $row) {
            $dimensionsChanging = $validated['cells_count'] !== $row->cells_count
                || $validated['flats_count'] !== $row->flats_count;

            if ($dimensionsChanging) {
                $blockReason = $this->lockedRowBlockReason(
                    $row,
                    'messages.row_cannot_resize_has_pallets',
                    'messages.row_cannot_resize_has_history',
                );

                if ($blockReason !== null) {
                    return redirect()->route('admin.rows.edit', $row)->withErrors(['cells_count' => $blockReason]);
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
                return redirect()->route('admin.rows.index', $request->query())->withErrors(['row' => $blockReason]);
            }

            $row->delete();

            return redirect()->route('admin.rows.index', $request->query());
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
