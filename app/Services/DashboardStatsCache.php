<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches dashboard stats (occupancy/expiring/activity — see BuildsDashboardStats)
 * behind a version number, so every cached variant (any today/expiring_days/
 * product_id combination) can be invalidated at once via flush() without the
 * cache store needing to support tags — the app's default `database` store
 * doesn't. Call flush() from any write path that changes cell occupancy, pallet
 * expiration dates, or cell status log activity; see CellStatusLogObserver,
 * RowObserver, and PalletObserver for the current call sites.
 */
class DashboardStatsCache
{
    private const string VERSION_KEY = 'dashboard-stats:version';

    /**
     * @template TReturn of array<string, mixed>
     *
     * @param  array<string, mixed>  $keyParts
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function remember(array $keyParts, Closure $callback): array
    {
        $key = 'dashboard-stats:'.self::currentVersion().':'.md5(serialize($keyParts));

        return Cache::remember($key, config('dashboard_cache.ttl'), $callback);
    }

    /**
     * Invalidate every cached dashboard stats variant.
     */
    public static function flush(): void
    {
        self::ensureVersionExists();

        Cache::increment(self::VERSION_KEY);
    }

    private static function currentVersion(): int
    {
        self::ensureVersionExists();

        return (int) Cache::get(self::VERSION_KEY);
    }

    /**
     * Cache::increment() on the database store, given no existing row, inserts
     * one holding the increment amount itself rather than 1 + that amount — so
     * flush() must guarantee the row exists (via the no-op-if-present add())
     * before incrementing, or a flush racing the very first remember() could
     * land on the same "version 1" key it was meant to invalidate.
     */
    private static function ensureVersionExists(): void
    {
        Cache::add(self::VERSION_KEY, 1);
    }
}
