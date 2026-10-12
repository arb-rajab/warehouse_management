<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsDashboardStats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowDashboardRequest;
use App\Http\Resources\MatchingPalletResource;
use App\Models\Pallet;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsDashboardStats;

    /**
     * Most pallets the selected-product list shows; the dashboard links to
     * the full pallets page beyond this.
     */
    public const int SELECTED_PRODUCT_PALLETS_LIMIT = 50;

    public function index(ShowDashboardRequest $request): Response
    {
        $today = today();
        $customExpiringDays = $request->integer('expiring_days') ?: self::DEFAULT_CUSTOM_EXPIRING_DAYS;
        $staleDays = $request->integer('stale_days') ?: self::DEFAULT_CUSTOM_STALE_DAYS;
        $productIds = $request->productIds();

        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => $this->buildDashboardStats($today, $customExpiringDays, $staleDays, $productIds),
            'today' => $today->toDateString(),
            'weekStart' => $this->dashboardWeekStart($today)->toDateString(),
            'productPallets' => $productIds === null ? [] : MatchingPalletResource::collection(
                Pallet::query()
                    ->listedForAdmin()
                    ->whereIn('product_id', $productIds)
                    ->limit(self::SELECTED_PRODUCT_PALLETS_LIMIT)
                    ->get(),
            ),
            'filters' => [
                'product_id' => $productIds,
            ],
            'filterOptions' => [
                'products' => Product::selectedOptions($productIds ?? []),
            ],
        ]);
    }
}
