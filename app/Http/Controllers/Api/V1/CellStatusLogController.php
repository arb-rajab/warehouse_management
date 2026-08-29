<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterCellStatusLogsRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Models\CellStatusLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CellStatusLogController extends Controller
{
    public function index(FilterCellStatusLogsRequest $request): AnonymousResourceCollection
    {
        $logs = CellStatusLog::query()
            ->forListing()
            ->filtered($request)
            ->sorted($request)
            ->paginate(20);

        CellStatusLog::attachNextLogs($logs->getCollection());

        return CellStatusLogResource::collection($logs);
    }
}
