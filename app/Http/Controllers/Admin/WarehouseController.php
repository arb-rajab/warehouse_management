<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FlashesAdminSuccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterWarehousesRequest;
use App\Http\Requests\Admin\SearchWarehousesRequest;
use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\WarehouseSummaryResource;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The warehouses the WMS manages. Same `role:admin` gate as rows; there is
 * no per-user warehouse scoping.
 */
class WarehouseController extends Controller
{
    use FlashesAdminSuccess;

    public function index(FilterWarehousesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Admin/Warehouses/Index', [
            'warehouses' => $this->paginated(WarehouseResource::collection(
                Warehouse::query()
                    ->select(['id', 'name', 'code', 'address'])
                    ->withCount('rows')
                    ->orderBy('name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    /**
     * The admin warehouse filter's searchable list, fetched on demand like
     * the product filter's (`Admin\ProductController::search()`); the labels
     * of already-selected warehouses come from `Warehouse::selectedOptions()`.
     *
     * @return array<string, mixed>
     */
    public function search(SearchWarehousesRequest $request): array
    {
        return $this->paginated(WarehouseSummaryResource::collection(
            Warehouse::query()
                ->select(['id', 'name'])
                ->searchByName($request->string('q')->value())
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
        ));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());
        $this->flashAdminSuccess('warehouse_has_been_created_successfully');

        return redirect()->route('admin.warehouses.index');
    }

    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('Admin/Warehouses/Edit', [
            'warehouse' => new WarehouseResource($warehouse->loadCount('rows')),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());
        $this->flashAdminSuccess('warehouse_has_been_updated_successfully');

        return redirect()->route('admin.warehouses.index');
    }

    /**
     * A warehouse that still has rows is not deleted: the rows (and their
     * cells, pallets and history) must be moved or deleted first. The check
     * runs under a lock on the warehouse, so a row created at the same moment
     * either waits for the delete or is seen by the check; the
     * `restrictOnDelete` foreign key is the last line behind it.
     */
    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        return DB::transaction(function () use ($request, $warehouse) {
            Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);

            if ($warehouse->hasRows()) {
                return $this->redirectPreservingQuery('admin.warehouses.index', $request)
                    ->withErrors(['warehouse' => __('messages.warehouse_cannot_delete_has_rows')]);
            }

            $warehouse->delete();
            $this->flashAdminSuccess('warehouse_has_been_deleted_successfully');

            return $this->redirectPreservingQuery('admin.warehouses.index', $request);
        });
    }
}
