<?php

namespace App\Observers;

use App\Models\Pallet;
use App\Services\DashboardStatsCache;

/**
 * Every pallet-mutating write that goes through PalletActionService also writes a
 * CellStatusLog in the same transaction, which is the flush() trigger
 * CellStatusLogObserver relies on (see its own doc comment). This observer is the
 * safety net for any Pallet write that reaches the database without going through
 * that service — a seeder/import inserting rows directly being the current example
 * — so the dashboard's occupancy/expiring counts can't sit stale (beyond
 * DashboardStatsCache's TTL) after one of those. See RowObserver and
 * CellStatusLogObserver for the other two flush() triggers.
 */
class PalletObserver
{
    public function created(Pallet $pallet): void
    {
        DashboardStatsCache::flush();
    }

    public function updated(Pallet $pallet): void
    {
        if (! $pallet->wasChanged(['expiration_date', 'product_id', 'cell_id'])) {
            return;
        }

        DashboardStatsCache::flush();
    }

    public function deleted(Pallet $pallet): void
    {
        DashboardStatsCache::flush();
    }
}
