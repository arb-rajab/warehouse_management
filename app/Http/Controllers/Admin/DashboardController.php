<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowDashboardRequest;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The fixed expiring-soon windows shown as their own cards, alongside the caller-adjustable custom one.
     *
     * @var list<int>
     */
    private const array EXPIRING_WINDOW_DAYS = [7, 14, 30, 60];

    /**
     * The custom card's default day count — distinct from the 7-day fixed card already in
     * EXPIRING_WINDOW_DAYS.
     */
    private const int DEFAULT_CUSTOM_EXPIRING_DAYS = 45;

    public function index(ShowDashboardRequest $request): Response
    {
        $today = today();
        $customExpiringDays = $request->integer('expiring_days') ?: self::DEFAULT_CUSTOM_EXPIRING_DAYS;
        $startOfWeek = $today->copy()->startOfWeek();

        $productIds = $request->productIds();

        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => [
                'occupancy' => $this->occupancy($productIds),
                'expiring' => $this->expiring($today, $customExpiringDays, $productIds),
                'activity_today' => $this->activityCounts(CellStatusLog::query()->whereDate('created_at', $today), $productIds),
                'activity_week' => $this->activityCounts(CellStatusLog::query()->whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()]), $productIds),
            ],
            'today' => $today->toDateString(),
            'weekStart' => $startOfWeek->toDateString(),
            'filters' => [
                'product_id' => $productIds,
            ],
            'filterOptions' => [
                'products' => Product::filterOptions(),
            ],
        ]);
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{empty: int, full: int, opened: int}
     */
    private function occupancy(?array $productIds): array
    {
        if ($productIds !== null) {
            return [
                'empty' => 0,
                'full' => Cell::query()->where('state', CellState::Full)->whereHas('pallet', fn ($q) => $q->whereIn('product_id', $productIds))->count(),
                'opened' => Cell::query()->where('state', CellState::Opened)->whereHas('pallet', fn ($q) => $q->whereIn('product_id', $productIds))->count(),
            ];
        }

        $byState = Cell::query()->select('state')->selectRaw('count(*) as count')->groupBy('state')->pluck('count', 'state');

        return [
            'empty' => (int) ($byState[CellState::Empty->value] ?? 0),
            'full' => (int) ($byState[CellState::Full->value] ?? 0),
            'opened' => (int) ($byState[CellState::Opened->value] ?? 0),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{expired: int, windows: list<array{days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}}
     */
    private function expiring(CarbonImmutable $today, int $customDays, ?array $productIds): array
    {
        $expired = Pallet::query()->whereDate('expiration_date', '<', $today);

        if ($productIds !== null) {
            $expired->whereIn('product_id', $productIds);
        }

        return [
            'expired' => $expired->count(),
            'windows' => array_map(
                fn (int $days) => $this->expiringWindow($today, $days, $productIds),
                self::EXPIRING_WINDOW_DAYS,
            ),
            'custom' => $this->expiringWindow($today, $customDays, $productIds),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{days: int, until: string, count: int}
     */
    private function expiringWindow(CarbonImmutable $today, int $days, ?array $productIds): array
    {
        $until = $today->copy()->addDays($days);
        $query = Pallet::query()->whereBetween('expiration_date', [$today, $until]);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        return [
            'days' => $days,
            'until' => $until->toDateString(),
            'count' => $query->count(),
        ];
    }

    /**
     * @param  Builder<CellStatusLog>  $query
     * @param  list<int>|null  $productIds
     * @return array{stored: int, opened: int, emptied: int, transferred: int}
     */
    private function activityCounts(Builder $query, ?array $productIds): array
    {
        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        /** @var Collection<string, int> $counts */
        $counts = $query->select('action')->selectRaw('count(*) as count')->groupBy('action')->pluck('count', 'action');

        return [
            'stored' => (int) ($counts[CellLogAction::Stored->value] ?? 0),
            'opened' => (int) ($counts[CellLogAction::Opened->value] ?? 0),
            'emptied' => (int) ($counts[CellLogAction::Emptied->value] ?? 0),
            'transferred' => (int) ($counts[CellLogAction::TransferredOut->value] ?? 0) + (int) ($counts[CellLogAction::TransferredIn->value] ?? 0),
        ];
    }
}
