<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read Collection<int, CityTranslation> $city_translations */
class City extends Model
{
    public function getTranslation($field = '', $lang = false)
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $city_translation = $this->hasMany(CityTranslation::class)->where('lang', $lang)->first();

        return $city_translation != null ? $city_translation->$field : $this->$field;
    }

    /** @return HasMany<CityTranslation, $this> */
    public function city_translations(): HasMany
    {
        return $this->hasMany(CityTranslation::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
