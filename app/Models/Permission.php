<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /**
     * See Role::getTable(): the legacy store's permissions live in spatie's
     * configured `wms_permissions` table here.
     */
    public function getTable(): string
    {
        return config('permission.table_names.permissions');
    }
}
