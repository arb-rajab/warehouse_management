<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Services\DashboardStatsCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared dashboard stats computation, used by both the admin Inertia dashboard
 * and the mobile API dashboard endpoint so the two stay in lockstep.
 *
 * Every stat honours the optional warehouse filter (`$warehouseIds`, null for
 * every warehouse) the same way it honours the product filter: cells through
 * their row, pallets through their cell, logs through the cell they were
 * written against, and the product counts (low stock, inactive in the
 * warehouse) through the pallets those warehouses hold.
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
     * @param  list<int>|null  $warehouseIds
     * @return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     stale: array{days: int, count: int},
     *     low_stock: array{count: int},
     *     inactive_in_warehouse: array{count: int},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     *
     * @scramble-return array{
     *     occupancy: array{empty: int, full: int, opened: int},
     *     expiring: array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}},
     *     stale: array{days: int, count: int},
     *     low_stock: array{count: int},
     *     inactive_in_warehouse: array{count: int},
     *     activity_today: array{stored: int, opened: int, emptied: int, transferred: int},
     *     activity_week: array{stored: int, opened: int, emptied: int, transferred: int},
     * }
     */
    private function buildDashboardStats(CarbonImmutable $today, int $customExpiringDays, int $staleDays, ?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        return DashboardStatsCache::remember(
            ['today' => $today->toDateString(), 'customExpiringDays' => $customExpiringDays, 'staleDays' => $staleDays, 'productIds' => $productIds, 'warehouseIds' => $warehouseIds, 'productPublished' => $productPublished],
            function () use ($today, $customExpiringDays, $staleDays, $productIds, $warehouseIds, $productPublished): array {
                $startOfWeek = $this->dashboardWeekStart($today);

                return [
                    'occupancy' => $this->occupancy($productIds, $warehouseIds, $productPublished),
                    'expiring' => $this->expiring($today, $customExpiringDays, $productIds, $warehouseIds, $productPublished),
                    'stale' => $this->stale($today, $staleDays, $productIds, $warehouseIds, $productPublished),
                    'low_stock' => $this->lowStock($productIds, $warehouseIds, $productPublished),
                    'inactive_in_warehouse' => $this->inactiveInWarehouse($productIds, $warehouseIds, $productPublished),
                    'activity_today' => $this->activityCounts(CellStatusLog::query()->whereDate('created_at', $today)->forWarehouses($warehouseIds), $productIds, $productPublished),
                    'activity_week' => $this->activityCounts(CellStatusLog::query()->whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()])->forWarehouses($warehouseIds), $productIds, $productPublished),
                ];
            },
        );
    }

    private function dashboardWeekStart(CarbonImmutable $today): CarbonImmutable
    {
        return $today->copy()->startOfWeek();
    }

    /**
     * With a product filter an empty cell holds no matching product, so
     * `empty` is 0; the warehouse filter alone keeps those warehouses' empty
     * cells.
     *
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{empty: int, full: int, opened: int}
     */
    private function occupancy(?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        if ($productIds !== null || $productPublished !== null) {
            return [
                'empty' => 0,
                'full' => Cell::query()->where('state', CellState::Full)
                    ->forWarehouses($warehouseIds)
                    ->whereHas('pallet', fn ($q) => $this->applyProductFilters($q, $productIds, $productPublished))
                    ->count(),
                'opened' => Cell::query()->where('state', CellState::Opened)
                    ->forWarehouses($warehouseIds)
                    ->whereHas('pallet', fn ($q) => $this->applyProductFilters($q, $productIds, $productPublished))
                    ->count(),
            ];
        }

        $byState = Cell::query()->forWarehouses($warehouseIds)->select('state')->selectRaw('count(*) as count')->groupBy('state')->pluck('count', 'state');

        return [
            'empty' => (int) ($byState[CellState::Empty->value] ?? 0),
            'full' => (int) ($byState[CellState::Full->value] ?? 0),
            'opened' => (int) ($byState[CellState::Opened->value] ?? 0),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{expired: int, windows: list<array{months: int, days: int, until: string, count: int}>, custom: array{days: int, until: string, count: int}}
     */
    private function expiring(CarbonImmutable $today, int $customDays, ?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $expired = Pallet::query()->where('expiration_date', '<', $today)->forWarehouses($warehouseIds);
        $this->applyProductFilters($expired, $productIds, $productPublished);

        return [
            'expired' => $expired->count(),
            'windows' => array_map(
                fn (int $months) => $this->expiringMonthWindow($today, $months, $productIds, $warehouseIds, $productPublished),
                self::EXPIRING_WINDOW_MONTHS,
            ),
            'custom' => $this->expiringDayWindow($today, $customDays, $productIds, $warehouseIds, $productPublished),
        ];
    }

    /**
     * A fixed expiring-soon window expressed in calendar months (`addMonths`, not `addDays($months * 30)`,
     * so `until` lands on the correct calendar date regardless of month length).
     *
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{months: int, days: int, until: string, count: int}
     */
    private function expiringMonthWindow(CarbonImmutable $today, int $months, ?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $until = $today->copy()->addMonths($months);

        return [
            'months' => $months,
            'days' => (int) $today->diffInDays($until),
            ...$this->expiringWindowCounts($today, $until, $productIds, $warehouseIds, $productPublished),
        ];
    }

    /**
     * The caller-adjustable custom expiring-soon window, expressed in days.
     *
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{days: int, until: string, count: int}
     */
    private function expiringDayWindow(CarbonImmutable $today, int $days, ?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $until = $today->copy()->addDays($days);

        return [
            'days' => $days,
            ...$this->expiringWindowCounts($today, $until, $productIds, $warehouseIds, $productPublished),
        ];
    }

    /**
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{until: string, count: int}
     */
    private function expiringWindowCounts(CarbonImmutable $today, CarbonImmutable $until, ?array $productIds, ?array $warehouseIds, ?bool $productPublished): array
    {
        $query = Pallet::query()->whereBetween('expiration_date', [$today, $until])->forWarehouses($warehouseIds);
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
     * @param  list<int>|null  $warehouseIds
     * @return array{days: int, count: int}
     */
    private function stale(CarbonImmutable $today, int $days, ?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $query = Pallet::query()->where('created_at', '<=', $today->copy()->subDays($days))->forWarehouses($warehouseIds);
        $this->applyProductFilters($query, $productIds, $productPublished);

        return [
            'days' => $days,
            'count' => $query->count(),
        ];
    }

    /**
     * How many products currently have fewer pallets in the warehouse than
     * their configured minimum — see Product::minimumPallets() and
     * Admin\ProductController::lowStockExistsSubquery(), which mirrors this
     * comparison for the products index's own `low_stock` filter. A product
     * with no threshold configured never counts, whatever its pallet count.
     *
     * Filtered by `$productIds`/`$productPublished` directly (Product's own
     * `id`/`published` columns) rather than via
     * {@see self::applyProductFilters()}, which assumes a `product_id`
     * column/`product` relation that Product itself doesn't have.
     *
     * With a warehouse filter the minimum is compared with the pallets those
     * warehouses hold, so a product the selected warehouses are short of
     * counts even when another warehouse holds enough of it.
     *
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{count: int}
     */
    private function lowStock(?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $palletCount = Pallet::query()
            ->selectRaw('count(*)')
            ->whereColumn('pallets.product_id', 'products.id')
            ->forWarehouses($warehouseIds);

        $count = Product::query()
            ->whereHas('setting', fn ($q) => $q->whereNotNull('minimum_pallets'))
            ->whereRaw(
                '(select minimum_pallets from wms_product_settings where wms_product_settings.product_id = products.id) > ('.$palletCount->toSql().')',
                $palletCount->getBindings(),
            )
            ->when($productIds !== null, fn ($q) => $q->whereIn('id', $productIds))
            ->when($productPublished !== null, fn ($q) => $q->where('published', $productPublished))
            ->count();

        return ['count' => $count];
    }

    /**
     * Inactive products (`products.published = false`) that nonetheless have
     * at least one pallet in the warehouse — the store has stopped selling
     * them, but stock still physically occupies a cell. Uses the same
     * {@see Product::inWarehouse()} scope as `Admin\ProductController::index()`'s
     * `inactive` filter, so the dashboard count and the products-page filter
     * it links to can never disagree on which products match.
     *
     * Filtered by `$productIds`/`$productPublished` directly (Product's own
     * `id`/`published` columns), the same as {@see self::lowStock()}; with a
     * warehouse filter the pallet must be in one of those warehouses.
     *
     * @param  list<int>|null  $productIds
     * @param  list<int>|null  $warehouseIds
     * @return array{count: int}
     */
    private function inactiveInWarehouse(?array $productIds, ?array $warehouseIds, ?bool $productPublished = null): array
    {
        $count = Product::query()
            ->where('published', false)
            ->inWarehouse($warehouseIds)
            ->when($productIds !== null, fn ($q) => $q->whereIn('id', $productIds))
            ->when($productPublished !== null, fn ($q) => $q->where('published', $productPublished))
            ->count();

        return ['count' => $count];
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
