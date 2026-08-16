<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCellStatusLogsRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Models\CellStatusLog;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CellStatusLogController extends Controller
{
    public function index(FilterCellStatusLogsRequest $request): Response
    {
        $logs = CellStatusLog::query()
            ->select(CellStatusLog::SELECT_COLUMNS)
            ->with(CellStatusLog::WITH_DETAILS)
            ->filtered($request)
            ->sorted($request)
            ->paginate(25)
            ->withQueryString();

        CellStatusLog::attachNextLogs($logs->getCollection());

        return Inertia::render('Admin/CellStatusLogs/Index', [
            'logs' => $this->paginated(CellStatusLogResource::collection($logs)),
            'filters' => $request->only(['product_id', 'pallet_id', 'row_id', 'column_number', 'user_id', 'action', 'date_from', 'date_to', 'created_within_days', 'expiration_date_from', 'expiration_date_to', 'expires_within_days', 'sort_by', 'sort_direction']),
            'filterOptions' => [
                ...Row::filterOptions(),
                'products' => Product::filterOptions(),
                'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
                'actions' => array_column(CellLogAction::cases(), 'value'),
            ],
        ]);
    }
}
