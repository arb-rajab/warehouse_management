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
use Illuminate\Database\Eloquent\Model;
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
    private const array EXPIRING_WINDOW_MONTHS = [1, 2, 4, 6];

    /**
     * The custom card's default day count — distinct from the 1-month fixed card already in
     * EXPIRING_WINDOW_MONTHS. Defined on ExpiringSoonDefaults (not here) so
     * Admin\ProductController's "expiring soon" column can share it without using this trait.
     */
    public const int DEFAULT_CUSTOM_EXPIRING_DAYS = ExpiringSoonDefaults::CUSTOM_WINDOW_DAYS;

    /**
     * The custom stale-window card's default day count — see StaleSoonDefaults'
     * doc comment for why this is a controller-level default rather than a
     * Pallet model constant.
     */
    public const int DEFAULT_CUSTOM_STALE_DAYS = StaleSoonDefaults::CUSTOM_WINDOW_DAYS;

    /**
     * @param  list<int>|null  $productIds
     * @return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     stale: array{days: int, count: int},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     *
     * @scramble-return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     stale: array{days: int, count: int},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     */
    private function buildDashboardStats(CarbonImmutable $today, int $customExpiringDays, int $staleDays, ?array $productIds, ?bool $productPublished = null): array
    {
        return DashboardStatsCache::remember(
            ['today' => $today->toDateString(), 'customExpiringDays' => $customExpiringDays, 'staleDays' => $staleDays, 'productIds' => $productIds, 'productPublished' => $productPublished],
            function () use ($today, $customExpiringDays, $staleDays, $productIds, $productPublished): array {
                $startOfWeek = $this->dashboardWeekStart($today);

                return [
                    'occupancy' => $this->occupancy($productIds, $productPublished),
                    'expiring' => $this->expiring($today, $customExpiringDays, $productIds, $productPublished),
                    'stale' => $this->stale($today, $staleDays, $productIds, $productPublished),
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
                'full' => Cell::query()->where('state', CellState::Full)
                    ->whereHas('pallet', fn ($q) => $this->applyProductFilters($q, $productIds, $productPublished))
                    ->count(),
                'opened' => Cell::query()->where('state', CellState::Opened)
                    ->whereHas('pallet', fn ($q) => $this->applyProductFilters($q, $productIds, $productPublished))
                    ->count(),
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
     * @return array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}}
     */
    private function expiring(CarbonImmutable $today, int $customDays, ?array $productIds, ?bool $productPublished = null): array
    {
        $expired = Pallet::query()->where('expiration_date', '<', $today);
        $this->applyProductFilters($expired, $productIds, $productPublished);

        return [
            'expired' => $expired->count(),
            'windows' => array_map(
                fn (int $months) => $this->expiringMonthWindow($today, $months, $productIds, $productPublished),
                self::EXPIRING_WINDOW_MONTHS,
            ),
            'custom' => $this->expiringDayWindow($today, $customDays, $productIds, $productPublished),
        ];
    }

    /**
     * A fixed expiring-soon window expressed in calendar months (`addMonths`, not `addDays($months * 30)`,
     * so `until` lands on the correct calendar date regardless of month length).
     *
     * @param  list<int>|null  $productIds
     * @return array{months: int, days: int, until: string, count: int}
     */
    private function expiringMonthWindow(CarbonImmutable $today, int $months, ?array $productIds, ?bool $productPublished = null): array
    {
        $until = $today->copy()->addMonths($months);

        return [
            'months' => $months,
            'days' => (int) $today->diffInDays($until),
            ...$this->expiringWindowCounts($today, $until, $productIds, $productPublished),
        ];
    }

    /**
     * The caller-adjustable custom expiring-soon window, expressed in days.
     *
     * @param  list<int>|null  $productIds
     * @return array{days: int, until: string, count: int}
     */
    private function expiringDayWindow(CarbonImmutable $today, int $days, ?array $productIds, ?bool $productPublished = null): array
    {
        $until = $today->copy()->addDays($days);

        return [
            'days' => $days,
            ...$this->expiringWindowCounts($today, $until, $productIds, $productPublished),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{until: string, count: int}
     */
    private function expiringWindowCounts(CarbonImmutable $today, CarbonImmutable $until, ?array $productIds, ?bool $productPublished): array
    {
        $query = Pallet::query()->whereBetween('expiration_date', [$today, $until]);
        $this->applyProductFilters($query, $productIds, $productPublished);

        return [
            'until' => $until->toDateString(),
            'count' => $query->count(),
        ];
    }

    /**
     * The caller-adjustable "stale within days" card — mirrors Pallet::isStaleAfter()'s
     * definition (created_at at least $days ago) as a count query rather than
     * per-row hydration. There is no fixed stale threshold (see
     * .ai/rules/models.md); $days always comes from the caller.
     *
     * @param  list<int>|null  $productIds
     * @return array{days: int, count: int}
     */
    private function stale(CarbonImmutable $today, int $days, ?array $productIds, ?bool $productPublished = null): array
    {
        $query = Pallet::query()->where('created_at', '<=', $today->copy()->subDays($days));
        $this->applyProductFilters($query, $productIds, $productPublished);

        return [
            'days' => $days,
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
        $this->applyProductFilters($query, $productIds, $productPublished);

        /** @var Collection<string, int> $counts */
        $counts = $query->select('action')->selectRaw('count(*) as count')->groupBy('action')->pluck('count', 'action');

        return [
            'stored' => (int) ($counts[CellLogAction::Stored->value] ?? 0),
            'opened' => (int) ($counts[CellLogAction::Opened->value] ?? 0),
            'emptied' => (int) ($counts[CellLogAction::Emptied->value] ?? 0),
            'transferred' => (int) ($counts[CellLogAction::TransferredOut->value] ?? 0) + (int) ($counts[CellLogAction::TransferredIn->value] ?? 0),
        ];
    }

    /**
     * Apply the shared product-id/product-published dashboard filters to a query in place.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>|null  $productIds
     */
    private function applyProductFilters(Builder $query, ?array $productIds, ?bool $productPublished): void
    {
        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        if ($productPublished !== null) {
            $query->whereHas('product', fn ($q) => $q->where('published', $productPublished));
        }
    }
}
