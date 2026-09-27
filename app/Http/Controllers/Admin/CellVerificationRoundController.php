<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CellVerificationReportNotAcceptableException;
use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptCellVerificationReportRequest;
use App\Http\Requests\Admin\FilterCellVerificationReportsRequest;
use App\Http\Requests\Admin\FilterCellVerificationRoundsRequest;
use App\Http\Resources\CellVerificationReportResource;
use App\Http\Resources\CellVerificationRoundResource;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use App\Services\CellVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CellVerificationRoundController extends Controller
{
    /**
     * List verification rounds — one per mobile worker's walkthrough. Each
     * round's own reports are viewed via show(), not listed here (a given
     * user's reports across every round are also listed on their admin user
     * page, see UserController::show()).
     */
    public function index(FilterCellVerificationRoundsRequest $request): InertiaResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $rounds = CellVerificationRound::query()
            ->select(CellVerificationRound::SELECT_COLUMNS)
            ->with(['user:id,name', 'rows:id,letter'])
            ->withCount('reports')
            ->filtered($request)
            ->sorted($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/CellVerificationRounds/Index', [
            'rounds' => $this->paginated(CellVerificationRoundResource::collection($rounds)),
            'filters' => [
                ...$request->only(['user_id', 'completed', 'date_from', 'date_to', 'created_within_days', 'sort_direction']),
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'users' => User::filterOptions(),
            ],
        ]);
    }

    /**
     * Show one round and the reports made during it, filterable/paginated on
     * their own — this is the only place a round's reports are listed scoped
     * to that one round (UserController::show() lists a user's reports
     * across every round instead, unfiltered).
     */
    public function show(FilterCellVerificationReportsRequest $request, CellVerificationRound $cellVerificationRound): InertiaResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $reports = CellVerificationReport::query()
            ->select(CellVerificationReport::SELECT_COLUMNS)
            ->with(CellVerificationReport::WITH_DETAILS)
            ->where('cell_verification_round_id', $cellVerificationRound->id)
            ->filtered($request)
            ->sorted($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/CellVerificationRounds/Show', [
            'round' => new CellVerificationRoundResource($cellVerificationRound->loadMissing(['user:id,name', 'rows:id,letter'])->loadCount('reports')),
            'reports' => $this->paginated(CellVerificationReportResource::collection($reports)),
            'filters' => [
                ...$request->only(['cell_id', 'row_id', 'column_number', 'product_id', 'is_correct', 'date_from', 'date_to', 'created_within_days', 'sort_direction']),
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                ...Row::filterOptions(),
                'products' => Product::selectedOptions($request->productIds() ?? []),
            ],
        ]);
    }

    /**
     * Export the given round's currently filtered/sorted reports as CSV,
     * streamed rather than loaded fully into memory.
     *
     * Chunked (not chunkById()) so the query keeps the exact order sorted()
     * applied — chunkById() forces its own order by id and would silently
     * override the caller's chosen sort_direction. Reports are append-only
     * (see CellVerificationReport's docblock), so chunk()'s offset-based
     * paging can't skip/repeat rows across chunks the way it could for a
     * mutable table.
     */
    public function exportReports(FilterCellVerificationReportsRequest $request, CellVerificationRound $cellVerificationRound): StreamedResponse
    {
        return response()->streamDownload(function () use ($request, $cellVerificationRound) {
            $csv = Writer::createFromPath('php://output', 'w');
            $csv->insertOne([
                'id', 'row', 'cell_number', 'flat_number',
                'is_correct', 'expected_cell_state', 'expected_product', 'expected_boxes_count',
                'expected_expiration_date', 'reported_cell_state', 'reported_product',
                'reported_boxes_count', 'reported_expiration_date', 'note', 'reported_at', 'accepted_at',
            ]);

            CellVerificationReport::query()
                ->select(CellVerificationReport::SELECT_COLUMNS)
                ->with(CellVerificationReport::WITH_DETAILS)
                ->where('cell_verification_round_id', $cellVerificationRound->id)
                ->filtered($request)
                ->sorted($request)
                ->chunk(500, function (Collection $reports) use ($csv) {
                    foreach ($reports as $report) {
                        $csv->insertOne([
                            $report->id,
                            $report->cell->row->letter,
                            $report->cell->cell_number,
                            $report->cell->flat_number,
                            $report->is_correct ? 'yes' : 'no',
                            $report->expected_cell_state->value,
                            $report->expectedProduct?->name,
                            $report->expected_boxes_count,
                            $report->expected_expiration_date?->toDateString(),
                            $report->reported_cell_state?->value,
                            $report->reportedProduct?->name,
                            $report->reported_boxes_count,
                            $report->reported_expiration_date?->toDateString(),
                            $report->note,
                            $report->created_at->toIso8601String(),
                            $report->accepted_at?->toIso8601String(),
                        ]);
                    }
                });
        }, "cell-verification-round-{$cellVerificationRound->id}-reports.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Accept an incorrect report, updating its cell to match what was reported
     * (see CellVerificationService::acceptReport()). A refusal — the cell
     * changed since, its row is being verified again, the report was already
     * accepted — is surfaced as a non-field `action` error for the page's
     * ActionErrorBanner, since that state can change after the page rendered.
     */
    public function acceptReport(AcceptCellVerificationReportRequest $request, CellVerificationReport $cellVerificationReport, CellVerificationService $cellVerifications): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $redirect = $this->redirectAfterAccept($request, $cellVerificationReport);

        try {
            $cellVerifications->acceptReport($cellVerificationReport, $user->id);
        } catch (CellVerificationReportNotAcceptableException|InvalidSlotStateException $e) {
            return $redirect->withErrors(['action' => $e->getMessage()]);
        }

        return $redirect;
    }

    /**
     * Triggered from both CellVerificationRounds/Show.vue (the default) and
     * Users/Show.vue ('return_to' => 'user'), which the Referer header/session
     * can't reliably tell apart — same as CellStatusLogController::
     * redirectAfterAcknowledge(). Each target is the report's own round or
     * reporter, never a request-supplied id.
     */
    private function redirectAfterAccept(AcceptCellVerificationReportRequest $request, CellVerificationReport $report): RedirectResponse
    {
        if ($request->input('return_to') === 'user') {
            return $this->redirectPreservingQuery('admin.users.show', $request, ['user' => $report->user_id]);
        }

        return $this->redirectPreservingQuery('admin.cell-verification-rounds.show', $request, [
            'cellVerificationRound' => $report->cell_verification_round_id,
        ]);
    }
}
