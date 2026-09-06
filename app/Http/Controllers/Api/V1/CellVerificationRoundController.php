<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCellVerificationRoundsRequest;
use App\Http\Resources\CellVerificationRoundResource;
use App\Models\CellVerificationRound;
use App\Models\User;
use App\Services\CellVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            ->ownedBy($user->id)
            ->withCount('reports')
            ->when($request->boolean('only_unfinished'), fn (Builder $query) => $query->unfinished())
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return CellVerificationRoundResource::collection($rounds);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $round = $this->cellVerifications->startRound($user->id);

        return (new CellVerificationRoundResource($round))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(Request $request, CellVerificationRound $cellVerificationRound): CellVerificationRoundResource
    {
        /** @var User $user */
        $user = $request->user();

        $round = $this->cellVerifications->completeRound($cellVerificationRound, $user->id);

        return new CellVerificationRoundResource($round);
    }
}
