<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCellVerificationReportsRequest;
use App\Http\Resources\CellVerificationReportResource;
use App\Models\CellVerificationReport;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CellVerificationReportController extends Controller
{
    use BuildsCellLogFilterOptions;

    private const array FILTER_FIELDS = [
        'cell_verification_round_id', 'cell_id', 'row_id', 'column_number',
        'user_id', 'product_id', 'is_correct', 'date_from', 'date_to',
        'created_within_days', 'sort_direction',
    ];

    public function index(FilterCellVerificationReportsRequest $request): InertiaResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $reports = CellVerificationReport::query()
            ->with(CellVerificationReport::WITH_DETAILS)
            ->filtered($request)
            ->sorted($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/CellVerificationReports/Index', [
            'reports' => $this->paginated(CellVerificationReportResource::collection($reports)),
            'filters' => [
                ...$request->only(self::FILTER_FIELDS),
                'per_page' => $perPage,
            ],
            'filterOptions' => $this->productRowUserActionFilterOptions($request->productIds()),
        ]);
    }

    /**
     * Export the currently filtered/sorted reports as CSV, streamed rather
     * than loaded fully into memory.
     */
    public function exportCsv(FilterCellVerificationReportsRequest $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $csv = Writer::createFromPath('php://output', 'w');
            $csv->insertOne([
                'id', 'verification_round_id', 'row', 'cell_number', 'flat_number',
                'is_correct', 'expected_cell_state', 'expected_product', 'expected_boxes_count',
                'expected_expiration_date', 'reported_cell_state', 'reported_product',
                'reported_boxes_count', 'reported_expiration_date', 'note', 'reported_by', 'reported_at',
            ]);

            $reports = CellVerificationReport::query()
                ->with(CellVerificationReport::WITH_DETAILS)
                ->filtered($request)
                ->sorted($request)
                ->cursor();

            foreach ($reports as $report) {
                $csv->insertOne([
                    $report->id,
                    $report->cell_verification_round_id,
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
                    $report->user->name,
                    $report->created_at->toIso8601String(),
                ]);
            }
        }, 'cell-verification-reports.csv', ['Content-Type' => 'text/csv']);
    }
}
