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

            if ($dimensionsChanging && $this->lockedRowHasPallets($row)) {
                return back()->withErrors(['cells_count' => __('messages.row_cannot_resize_has_pallets')]);
            }

            $row->update($validated);

            return redirect()->route('admin.rows.show', $row);
        });
    }

    public function destroy(Request $request, Row $row): RedirectResponse
    {
        return DB::transaction(function () use ($request, $row) {
            if ($this->lockedRowHasPallets($row)) {
                return back()->withErrors(['row' => __('messages.row_cannot_delete_has_pallets')]);
            }

            $row->delete();

            return redirect()->route('admin.rows.index', $request->query());
        });
    }

    private function lockedRowHasPallets(Row $row): bool
    {
        $row->cells()->lockForUpdate()->get();

        return $row->hasPallets();
    }
}
