<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    public function getTranslation(string $field = '', string|false|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $page_translation = $this->hasMany(PageTranslation::class)->where('lang', $lang)->first();

        return $page_translation != null ? $page_translation->$field : $this->$field;
    }

    /** @return HasMany<PageTranslation, $this> */
    public function page_translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
