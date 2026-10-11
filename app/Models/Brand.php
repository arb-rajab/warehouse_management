<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{

  protected $with = ['brand_translations'];

  public function getTranslation($field = '', $lang = false){
      $lang = $lang == false ? App::getLocale() : $lang;
      $brand_translation = $this->brand_translations->where('lang', $lang)->first();
      return $brand_translation != null ? $brand_translation->$field : $this->$field;
  }

  /** @return HasMany<BrandTranslation, $this> */
    public function brand_translations(): HasMany{
    return $this->hasMany(BrandTranslation::class);
  }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }
}
