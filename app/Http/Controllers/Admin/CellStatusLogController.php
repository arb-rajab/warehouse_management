<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCellStatusLogsRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Models\CellStatusLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CellStatusLogController extends Controller
{
    use BuildsCellLogFilterOptions;

    public function index(FilterCellStatusLogsRequest $request): Response
    {
        $logs = CellStatusLog::query()
            ->forListing()
            ->filtered($request)
            ->sorted($request)
            ->paginate(25)
            ->withQueryString();

        CellStatusLog::attachNextLogs($logs->getCollection());

        return Inertia::render('Admin/CellStatusLogs/Index', [
            'logs' => $this->paginated(CellStatusLogResource::collection($logs)),
            'filters' => $request->only(['product_id', 'pallet_id', 'row_id', 'column_number', 'user_id', 'action', 'date_from', 'date_to', 'created_within_days', 'expiration_date_from', 'expiration_date_to', 'expires_within_days', 'sort_by', 'sort_direction', 'flagged']),
            'filterOptions' => $this->productRowUserActionFilterOptions($request->productIds()),
        ]);
    }

    /**
     * Acknowledge every currently-unacknowledged rule-based flag on a log entry.
     */
    public function acknowledgeFlags(Request $request, CellStatusLog $cellStatusLog): RedirectResponse
    {
        $cellStatusLog->flags()->whereNull('acknowledged_at')->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.cell-logs.index', $request->query());
    }
}
