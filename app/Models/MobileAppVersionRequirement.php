<?php

namespace App\Models;

use Database\Factories\MobileAppVersionRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $minimum_version
 */
#[Fillable(['minimum_version'])]
class MobileAppVersionRequirement extends Model
{
    /** @use HasFactory<MobileAppVersionRequirementFactory> */
    use HasFactory;

    /**
     * Cache key for the single row's minimum_version value. Invalidated by
     * SetMinimumAppVersionCommand, the only writer to this table.
     */
    public const MINIMUM_VERSION_CACHE_KEY = 'mobile_app_version_requirement.minimum_version';

    /**
     * The command upserts the single existing row, so there is at most one
     * requirement stored — null means no minimum has been configured yet,
     * which callers treat as "don't restrict anything".
     *
     * This is read on every API request (EnsureMinimumAppVersion), ahead of
     * auth:sanctum, so it's cached rather than hitting the DB every time.
     * Cache::remember() never persists a null result, so an unconfigured
     * minimum simply re-queries next time rather than poisoning the cache.
     */
    public static function minimumVersion(): ?string
    {
        return Cache::rememberForever(
            self::MINIMUM_VERSION_CACHE_KEY,
            fn () => static::query()->value('minimum_version'),
        );
    }
}
