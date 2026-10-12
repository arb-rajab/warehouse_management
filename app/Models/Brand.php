<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

/**
 * @property int $id
 * @property string $name
 * @property string|null $logo
 * @property int $top
 * @property string|null $slug
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $serial
 * @property-read Collection<int, BrandTranslation> $brand_translations
 */
class Brand extends Model
{
    protected $with = ['brand_translations'];

    public function getTranslation(string $field = '', string|false|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $brand_translation = $this->brand_translations->where('lang', $lang)->first();

        return $brand_translation != null ? $brand_translation->getAttribute($field) : $this->getAttribute($field);
    }

    /** @return HasMany<BrandTranslation, $this> */
    public function brand_translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
