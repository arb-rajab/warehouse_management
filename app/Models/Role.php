<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The legacy store's roles: spatie's role model, held to the `store` guard.
 *
 * Both apps' roles share one table, spatie's configured `wms_roles` (see
 * .ai/rules/legacy-albaraka-holland.md section 3), told apart only by
 * `guard_name` (WMS's `admin` is `web`). The global scope keeps every store
 * lookup on this class (the role screens' listing, editing and deletion by
 * id) from reaching a WMS role, and `$guard_name` gives Role::create() the
 * store's guard whichever guard the request happens to have as its default.
 */
class Role extends SpatieRole
{
    public const SUPER_ADMIN = 'Super Admin';

    protected $with = ['role_translations'];

    protected string $guard_name = 'store';

    protected static function booted(): void
    {
        static::addGlobalScope('store_guard', function (Builder $query): void {
            $query->where($query->qualifyColumn('guard_name'), 'store');
        });
    }

    /**
     * Roles a staff member can be given: everything but Super Admin.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeExcludingSuperAdmin(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('name'), '!=', self::SUPER_ADMIN);
    }

    public function getTranslation($field = '', $lang = false){
        $lang = $lang == false ? App::getLocale() : $lang;
        $role_translation = $this->role_translations->where('lang', $lang)->first();
        return $role_translation != null ? $role_translation->$field : $this->$field;
    }

    public function role_translations(){
      return $this->hasMany(RoleTranslation::class);
    }
}
