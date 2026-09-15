<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCellVerificationRoundsRequest;
use App\Http\Requests\Api\V1\StoreCellVerificationRoundRequest;
use App\Http\Resources\CellVerificationRoundResource;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\User;
use App\Services\CellVerificationService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CellVerificationRoundController extends Controller
{
    public function __construct(private readonly CellVerificationService $cellVerifications) {}

    /**
     * List the authenticated user's own verification rounds, most recent
     * first, so the mobile app can offer resuming an unfinished one.
     */
    public function index(IndexCellVerificationRoundsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $rounds = CellVerificationRound::query()
            ->select(CellVerificationRound::SELECT_COLUMNS)
            ->with('rows:id,letter')
            ->ownedBy($user->id)
            ->withCount('reports')
            ->when($request->boolean('only_unfinished'), fn (Builder $query) => $query->unfinished())
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return CellVerificationRoundResource::collection($rounds);
    }

    /**
     * Show one of the authenticated user's own rounds, with its reports
     * loaded in the order they were reported, so the mobile app can let a
     * worker review a past (completed or still-open) walk cell by cell.
     */
    public function show(CellVerificationRound $cellVerificationRound): CellVerificationRoundResource
    {
        Gate::authorize('view', $cellVerificationRound);

        $cellVerificationRound->loadCount('reports');
        $cellVerificationRound->load(['rows:id,letter']);
        $cellVerificationRound->load(['reports' => fn ($query) => $query
            ->with(CellVerificationReport::WITH_DETAILS)
            ->orderBy('created_at')
            ->orderBy('id')]);

        return new CellVerificationRoundResource($cellVerificationRound);
    }

    /**
     * Start a round over the rows the worker is about to walk, or over the
     * whole warehouse when `row_ids` is omitted. Those rows are claimed
     * exclusively until the round completes: no other round may include any of
     * them, and pallet actions on their cells are refused meanwhile — so a
     * warehouse-wide round holds everything until it is finished.
     */
    #[DocumentedResponse(409, description: 'One or more of the requested rows are already covered by another unfinished round (`error_code`: `rows_already_in_active_round`).', type: 'array{message: string, error_code: string}')]
    public function store(StoreCellVerificationRoundRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $round = $this->cellVerifications->startRound($user->id, $request->rowIds());

        return (new CellVerificationRoundResource($round->load('rows:id,letter')))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(CellVerificationRound $cellVerificationRound): CellVerificationRoundResource
    {
        Gate::authorize('update', $cellVerificationRound);

        $round = $this->cellVerifications->completeRound($cellVerificationRound);

        return new CellVerificationRoundResource($round->load('rows:id,letter'));
    }
}
