<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Stats Cache TTL
    |--------------------------------------------------------------------------
    |
    | Safety-net expiry (seconds) for cached dashboard stats. Normal
    | invalidation is immediate — App\Services\DashboardStatsCache::flush()
    | is called by CellStatusLogObserver (every store/open/empty/transfer)
    | and RowObserver (row create/layout change), which cover every write
    | that can change occupancy, expiring, or activity counts. This TTL only
    | guards against a write path that invalidation was missed for.
    |
    */

    'ttl' => (int) env('DASHBOARD_CACHE_TTL', 300),

];
