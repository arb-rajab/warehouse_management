<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterCellStatusLogsRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Models\CellStatusLog;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CellStatusLogController extends Controller
{
    public function index(FilterCellStatusLogsRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $query = CellStatusLog::query()
            ->forListing()
            ->filtered($request)
            ->sorted($request);

        // This endpoint deliberately doesn't eager-load WITH_FLAG_DETAILS for
        // a mobile worker (see CellStatusLog::WITH_FLAG_DETAILS), so
        // CellStatusLogResource never has a `flags_count` alias or a loaded
        // `flags` relation to compute `flagged` from — an admin caller of
        // this endpoint would otherwise fall through to CellStatusLog::flagged()'s
        // slow path (a dedicated exists() query per row). withCount() alone
        // doesn't reveal anything: the resource still withholds the
        // `flagged`/`flags` keys from a non-admin viewer regardless.
        if ($viewer instanceof User && $viewer->isAdmin()) {
            $query->withCount('flags');
        }

        $logs = $query->paginate(20);

        CellStatusLog::attachNextLogs($logs->getCollection());

        return CellStatusLogResource::collection($logs);
    }
}
