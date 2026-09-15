<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Services\DashboardStatsCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared dashboard stats computation, used by both the admin Inertia dashboard
 * and the mobile API dashboard endpoint so the two stay in lockstep.
 */
trait BuildsDashboardStats
{
    /**
     * The fixed expiring-soon windows shown as their own cards, alongside the caller-adjustable custom one.
     *
     * @var list<int>
     */
    private const array EXPIRING_WINDOW_DAYS = [7, 14, 30, 60];

    /**
     * The custom card's default day count — distinct from the 7-day fixed card already in
     * EXPIRING_WINDOW_DAYS. Defined on ExpiringSoonDefaults (not here) so
     * Admin\ProductController's "expiring soon" column can share it without using this trait.
     */
    public const int DEFAULT_CUSTOM_EXPIRING_DAYS = ExpiringSoonDefaults::CUSTOM_WINDOW_DAYS;

    /**
     * @param  list<int>|null  $productIds
     * @return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     *
     * @scramble-return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     */
    private function buildDashboardStats(CarbonImmutable $today, int $customExpiringDays, ?array $productIds, ?bool $productPublished = null): array
    {
        return DashboardStatsCache::remember(
            ['today' => $today->toDateString(), 'customExpiringDays' => $customExpiringDays, 'productIds' => $productIds, 'productPublished' => $productPublished],
            function () use ($today, $customExpiringDays, $productIds, $productPublished): array {
                $startOfWeek = $this->dashboardWeekStart($today);

                return [
                    'occupancy' => $this->occupancy($productIds, $productPublished),
                    'expiring' => $this->expiring($today, $customExpiringDays, $productIds, $productPublished),
                    'activity_today' => $this->activityCounts(CellStatusLog::query()->whereDate('created_at', $today), $productIds, $productPublished),
                    'activity_week' => $this->activityCounts(CellStatusLog::query()->whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()]), $productIds, $productPublished),
                ];
            },
        );
    }

    private function dashboardWeekStart(CarbonImmutable $today): CarbonImmutable
    {
        return $today->copy()->startOfWeek();
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{empty: int, full: int, opened: int}
     */
    private function occupancy(?array $productIds, ?bool $productPublished = null): array
    {
        if ($productIds !== null || $productPublished !== null) {
            return [
                'empty' => 0,
                'full' => Cell::query()->where('state', CellState::Full)->whereHas('pallet', fn ($q) => $q
                    ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
                    ->when($productPublished !== null, fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('published', $productPublished))))->count(),
                'opened' => Cell::query()->where('state', CellState::Opened)->whereHas('pallet', fn ($q) => $q
                    ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
                    ->when($productPublished !== null, fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('published', $productPublished))))->count(),
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
    private function expiring(CarbonImmutable $today, int $customDays, ?array $productIds, ?bool $productPublished = null): array
    {
        $expired = Pallet::query()->whereDate('expiration_date', '<', $today);

        if ($productIds !== null) {
            $expired->whereIn('product_id', $productIds);
        }

        if ($productPublished !== null) {
            $expired->whereHas('product', fn ($q) => $q->where('published', $productPublished));
        }

        return [
            'expired' => $expired->count(),
            'windows' => array_map(
                fn (int $days) => $this->expiringWindow($today, $days, $productIds, $productPublished),
                self::EXPIRING_WINDOW_DAYS,
            ),
            'custom' => $this->expiringWindow($today, $customDays, $productIds, $productPublished),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{days: int, until: string, count: int}
     */
    private function expiringWindow(CarbonImmutable $today, int $days, ?array $productIds, ?bool $productPublished = null): array
    {
        $until = $today->copy()->addDays($days);
        $query = Pallet::query()->whereBetween('expiration_date', [$today, $until]);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        if ($productPublished !== null) {
            $query->whereHas('product', fn ($q) => $q->where('published', $productPublished));
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
    private function activityCounts(Builder $query, ?array $productIds, ?bool $productPublished = null): array
    {
        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        if ($productPublished !== null) {
            $query->whereHas('product', fn ($q) => $q->where('published', $productPublished));
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
