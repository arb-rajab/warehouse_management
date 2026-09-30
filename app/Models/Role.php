<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $with = ['role_translations'];

    /**
     * The legacy store read spatie's roles table through this plain model,
     * by its default name `roles`. Both apps' roles share one table here,
     * spatie's configured `wms_roles` (see .ai/rules/legacy-albaraka-holland.md
     * section 3), and `roles` does not exist.
     */
    public function getTable(): string
    {
        return config('permission.table_names.roles');
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
