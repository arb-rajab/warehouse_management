<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

class Attribute extends Model
{
    protected $with = ['attribute_translations'];

    protected $guarded = [];

    public function getTranslation(string $field = '', string|false $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $translation = $this->attribute_translations->where('lang', $lang)->first();

        return $translation !== null ? $translation->{$field} : $this->{$field};
    }

    /** @return HasMany<AttributeTranslation, $this> */
    public function attribute_translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }

    /** @return HasMany<AttributeValue, $this> */
    public function attribute_values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
