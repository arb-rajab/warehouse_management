<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcknowledgeCellStatusLogFlagsRequest;
use App\Http\Requests\Admin\FilterCellStatusLogsRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Models\CellStatusLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CellStatusLogController extends Controller
{
    use BuildsCellLogFilterOptions;

    public function index(FilterCellStatusLogsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        $logs = CellStatusLog::query()
            ->forListing()
            ->with(CellStatusLog::WITH_FLAG_DETAILS)
            ->filtered($request)
            ->sorted($request)
            ->paginate($perPage)
            ->withQueryString();

        CellStatusLog::attachNextLogs($logs->getCollection());

        return Inertia::render('Admin/CellStatusLogs/Index', [
            'logs' => $this->paginated(CellStatusLogResource::collection($logs)),
            'filters' => [
                ...$request->only(['product_id', 'pallet_id', 'row_id', 'column_number', 'user_id', 'action', 'date_from', 'date_to', 'created_within_days', 'expiration_date_from', 'expiration_date_to', 'expires_within_days', 'sort_by', 'sort_direction', 'flagged']),
                'per_page' => $perPage,
            ],
            'filterOptions' => $this->productRowUserActionFilterOptions($request->productIds()),
        ]);
    }

    /**
     * Acknowledge every currently-unacknowledged rule-based flag on a log entry.
     */
    public function acknowledgeFlags(AcknowledgeCellStatusLogFlagsRequest $request, CellStatusLog $cellStatusLog): RedirectResponse
    {
        $cellStatusLog->flags()->whereNull('acknowledged_at')->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return $this->redirectAfterAcknowledge($request, $cellStatusLog);
    }

    /**
     * Triggered from both CellStatusLogs/Index.vue (the default) and
     * Users/Show.vue ('return_to' => 'user') — the Referer header/session
     * can't reliably tell the two apart (see PalletController::redirectFor
     * for the same pattern), so the frontend sends it explicitly. The user
     * page redirected back to is always the log's own actor: Users/Show
     * only ever lists that one user's actions.
     */
    private function redirectAfterAcknowledge(AcknowledgeCellStatusLogFlagsRequest $request, CellStatusLog $cellStatusLog): RedirectResponse
    {
        if ($request->input('return_to') === 'user') {
            return $this->redirectPreservingQuery('admin.users.show', $request, ['user' => $cellStatusLog->user_id]);
        }

        return $this->redirectPreservingQuery('admin.cell-logs.index', $request);
    }
}
