<?php

namespace App\Observers;

use App\Models\ProductSetting;
use App\Services\DashboardStatsCache;

/**
 * `minimum_pallets` feeds the dashboard's low-stock count (see
 * BuildsDashboardStats), but writing it (Admin\ProductController::
 * updateMinimumPallets()) goes through `updateOrCreate()` on the settings
 * relation rather than any of the write paths DashboardStatsCache's other
 * observers already flush on — so without this, a newly-configured or
 * changed threshold would sit stale on the dashboard until the cache's TTL
 * expires.
 */
class ProductSettingObserver
{
    public function created(ProductSetting $setting): void
    {
        if ($setting->minimum_pallets !== null) {
            DashboardStatsCache::flush();
        }
    }

    public function updated(ProductSetting $setting): void
    {
        if (! $setting->wasChanged('minimum_pallets')) {
            return;
        }

        DashboardStatsCache::flush();
    }
}
