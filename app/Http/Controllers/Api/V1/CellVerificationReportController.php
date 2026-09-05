<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCellVerificationReportRequest;
use App\Http\Resources\CellVerificationReportResource;
use App\Models\CellVerificationRound;
use App\Models\User;
use App\Services\CellVerificationService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;

class CellVerificationReportController extends Controller
{
    private const array EAGER_LOAD = ['cell.row:id,letter', 'expectedProduct:id,name,image_url,boxes_count', 'reportedProduct:id,name,image_url,boxes_count'];

    public function __construct(private readonly CellVerificationService $cellVerifications) {}

    #[DocumentedResponse(403, description: 'The verification round belongs to another user.', type: 'array{message: string}')]
    #[DocumentedResponse(409, description: 'The verification round has already been completed (`error_code`: `verification_round_completed`).', type: 'array{message: string, error_code: string}')]
    public function store(StoreCellVerificationReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $round = CellVerificationRound::query()->findOrFail($request->integer('cell_verification_round_id'));

        $report = $this->cellVerifications->report($round, $request->integer('cell_id'), $user->id, [
            'is_correct' => $request->boolean('is_correct'),
            'reported_cell_state' => $request->input('reported_cell_state'),
            'reported_product_id' => $request->input('reported_product_id'),
            'reported_boxes_count' => $request->input('reported_boxes_count'),
            'reported_expiration_date' => $request->input('reported_expiration_date'),
            'note' => $request->input('note'),
        ]);

        return (new CellVerificationReportResource($report->load(self::EAGER_LOAD)))
            ->response()
            ->setStatusCode(201);
    }
}
