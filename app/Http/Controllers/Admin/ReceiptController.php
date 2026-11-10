<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterReceiptsRequest;
use App\Http\Resources\AdminReceiptResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\Warehouse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only admin pages for truck receipts: the list and one receipt with its
 * bill files and the pallets as written on the bill. Receipts are made and
 * finalized from the mobile app (see .ai/rules/receiving.md).
 */
class ReceiptController extends Controller
{
    /**
     * The receipts, newest first unless `sort_direction` says otherwise, with
     * their pallet and file counts.
     */
    public function index(FilterReceiptsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $direction = $request->input('sort_direction') === 'asc' ? 'asc' : 'desc';

        $receipts = Receipt::query()
            ->select(Receipt::SELECT_COLUMNS)
            ->with(Receipt::WITH_SUMMARY)
            ->withCount(['pallets', 'files'])
            ->ofStatus($request->status())
            ->forWarehouses($request->warehouseIds())
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Receipts/Index', [
            'receipts' => $this->paginated(ReceiptResource::collection($receipts)),
            'filters' => [
                ...$request->only(['warehouse_ids', 'status', 'sort_direction']),
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'warehouses' => Warehouse::selectedOptions($request->warehouseIds() ?? []),
            ],
        ]);
    }

    /**
     * One receipt: its details, bill files (each with its admin download
     * `url`) and pallets as written on the bill, in the order they were typed.
     */
    public function show(Receipt $receipt): Response
    {
        return Inertia::render('Admin/Receipts/Show', [
            'receipt' => new AdminReceiptResource($receipt->load(Receipt::withDetails())->loadCount(['pallets', 'files'])),
        ]);
    }
}
