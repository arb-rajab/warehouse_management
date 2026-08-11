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
        return CellStatusLogResource::collection(
            CellStatusLog::query()
                ->select(CellStatusLog::SELECT_COLUMNS)
                ->with(CellStatusLog::WITH_DETAILS)
                ->filtered($request)
                ->latest()
                ->paginate(20)
        );
    }
}
