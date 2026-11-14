<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterCellVerificationRoundsRequest;
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
     * first (`warehouse_ids[]` keeps those covering a row of those warehouses), so the mobile app can offer resuming an unfinished one.
     */
    public function index(FilterCellVerificationRoundsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $rounds = CellVerificationRound::query()
            ->select(CellVerificationRound::SELECT_COLUMNS)
            ->with(CellVerificationRound::WITH_ROWS)
            ->ownedBy($user->id)
            ->withCount('reports')
            ->when($request->boolean('only_unfinished'), fn (Builder $query) => $query->unfinished())
            ->forWarehouses($request->warehouseIds())
            ->latest('created_at')
            ->latest('id')
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
        $cellVerificationRound->load(CellVerificationRound::WITH_ROWS);
        $cellVerificationRound->load(['reports' => fn ($query) => $query
            ->with([...CellVerificationReport::WITH_DETAILS, ...CellVerificationReport::WITH_WAREHOUSE])
            ->orderBy('created_at')
            ->orderBy('id')]);

        return new CellVerificationRoundResource($cellVerificationRound);
    }

    /**
     * Start a round over the rows the worker is about to walk, or over every
     * row when `row_ids` is omitted; `warehouse_ids[]` narrows either to the
     * rows of those warehouses (a named row outside them is refused), so a
     * round over one warehouse is `warehouse_ids[]` alone. Those rows are
     * claimed exclusively until the round completes: no other round may
     * include any of them, and pallet actions on their cells are refused
     * meanwhile — so a round over everything holds it until it is finished.
     */
    #[DocumentedResponse(409, description: 'One or more of the requested rows are already covered by another unfinished round (`error_code`: `rows_already_in_active_round`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(422, description: 'The round would cover no rows, or a requested row no longer exists (`error_code`: `verification_round_scope_empty` / `verification_round_rows_not_found`).', type: 'array{message: string, error_code: string}')]
    public function store(StoreCellVerificationRoundRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $round = $this->cellVerifications->startRound($user->id, $request->rowIds(), $request->warehouseIds());

        return (new CellVerificationRoundResource($round->load(CellVerificationRound::WITH_ROWS)))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(CellVerificationRound $cellVerificationRound): CellVerificationRoundResource
    {
        Gate::authorize('update', $cellVerificationRound);

        $round = $this->cellVerifications->completeRound($cellVerificationRound);

        return new CellVerificationRoundResource($round->load(CellVerificationRound::WITH_ROWS));
    }
}
