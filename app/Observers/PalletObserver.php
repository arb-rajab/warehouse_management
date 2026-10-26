<?php

namespace App\Observers;

use App\Models\Pallet;
use App\Services\DashboardStatsCache;

/**
 * Invalidates dashboard stats on pallet creation/deletion and changes to product,
 * expiration, cell or remaining boxes. This also covers PalletActionService::update()
 * corrections that write no CellStatusLog, plus model writes from seeders/imports.
 * RowObserver and CellStatusLogObserver provide additional invalidation triggers;
 * the log observer defers its flush until the caller's transaction commits.
 */
class PalletObserver
{
    public function created(Pallet $pallet): void
    {
        DashboardStatsCache::flush();
    }

    public function updated(Pallet $pallet): void
    {
        if (! $pallet->wasChanged(['expiration_date', 'product_id', 'cell_id', 'remaining_boxes'])) {
            return;
        }

        DashboardStatsCache::flush();
    }

    public function deleted(Pallet $pallet): void
    {
        DashboardStatsCache::flush();
    }
}
