<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRowRequest;
use App\Http\Requests\UpdateRowRequest;
use App\Http\Resources\CellResource;
use App\Http\Resources\RowResource;
use App\Models\Cell;
use App\Models\Row;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Rows/Index', [
            'rows' => $this->paginated(RowResource::collection(
                Row::query()->select(['id', 'letter', 'cells_count', 'flats_count'])->paginate(20)
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
                    ->select(['id', 'cell_number', 'flat_number', 'state'])
                    ->with(Cell::WITH_CONTENTS)
                    ->orderedByCoordinates()
                    ->get()
            ),
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
        $row->update($request->validated());

        return redirect()->route('admin.rows.show', $row);
    }

    public function destroy(Row $row): RedirectResponse
    {
        if ($row->hasPallets()) {
            return back()->withErrors(['row' => 'Cannot delete a row that has pallets in it.']);
        }

        $row->delete();

        return redirect()->route('admin.rows.index');
    }
}
