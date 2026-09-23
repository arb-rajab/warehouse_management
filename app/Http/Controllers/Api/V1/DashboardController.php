<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\BuildsDashboardStats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowDashboardRequest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use BuildsDashboardStats;

    public function index(ShowDashboardRequest $request): JsonResponse
    {
        $today = today();
        $customExpiringDays = $request->integer('expiring_days') ?: self::DEFAULT_CUSTOM_EXPIRING_DAYS;
        $staleDays = $request->integer('stale_days') ?: self::DEFAULT_CUSTOM_STALE_DAYS;
        $productIds = $request->productIds();
        $productPublished = $request->productPublished();

        return response()->json([
            'stats' => $this->buildDashboardStats($today, $customExpiringDays, $staleDays, $productIds, $productPublished),
            'today' => $today->toDateString(),
            'weekStart' => $this->dashboardWeekStart($today)->toDateString(),
            'filters' => [
                'product_id' => $productIds,
                'product_status' => $request->input('product_status'),
            ],
        ]);
    }
}
