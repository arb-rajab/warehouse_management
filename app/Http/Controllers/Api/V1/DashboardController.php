<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\BuildsDashboardStats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowDashboardRequest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use BuildsDashboardStats;

    /**
     * @return JsonResponse<array{
     *     stats: array{
     *         occupancy: array{empty: int, full: int, opened: int},
     *         expiring: array{expired: int, windows: list<array{days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *         activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *         activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     *     },
     *     today: string,
     *     weekStart: string,
     *     filters: array{product_id: list<int>|null},
     * }>
     */
    public function index(ShowDashboardRequest $request): JsonResponse
    {
        $today = today();
        $customExpiringDays = $request->integer('expiring_days') ?: self::DEFAULT_CUSTOM_EXPIRING_DAYS;
        $productIds = $request->productIds();

        return response()->json([
            'stats' => $this->buildDashboardStats($today, $customExpiringDays, $productIds),
            'today' => $today->toDateString(),
            'weekStart' => $this->dashboardWeekStart($today)->toDateString(),
            'filters' => [
                'product_id' => $productIds,
            ],
        ]);
    }
}
