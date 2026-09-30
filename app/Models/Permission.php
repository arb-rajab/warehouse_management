<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * The legacy store's permissions: spatie's permission model, held to the
 * `store` guard, for the same reason as Role. They live in spatie's
 * configured `wms_permissions` table, next to WMS's own `web` ones.
 */
class Permission extends SpatiePermission
{
    protected string $guard_name = 'store';

    protected static function booted(): void
    {
        static::addGlobalScope('store_guard', function (Builder $query): void {
            $query->where($query->qualifyColumn('guard_name'), 'store');
        });
    }
}
