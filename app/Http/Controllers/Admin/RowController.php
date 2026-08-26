<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRowRequest;
use App\Http\Requests\UpdateRowRequest;
use App\Http\Resources\CellResource;
use App\Http\Resources\RowResource;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Row;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Rows/Index', [
            'rows' => $this->paginated(RowResource::collection(
                Row::query()
                    ->select(Row::SELECT_COLUMNS)
                    ->withExists(['cells as has_pallets' => fn ($query) => $query->has('pallet')])
                    ->paginate(20)
            )),
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
            'row' => new RowResource($row),
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
            'row' => new RowResource($row),
        ]);
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

    public function destroy(Row $row): RedirectResponse
    {
        return DB::transaction(function () use ($row) {
            if ($this->lockedRowHasPallets($row)) {
                return back()->withErrors(['row' => __('messages.row_cannot_delete_has_pallets')]);
            }

            $row->delete();

            return redirect()->route('admin.rows.index');
        });
    }

    private function lockedRowHasPallets(Row $row): bool
    {
        $row->cells()->lockForUpdate()->get();

        return $row->hasPallets();
    }
}
