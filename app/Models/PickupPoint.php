<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupPoint extends Model
{
    protected $with = ['pickup_point_translations'];

    public function getTranslation($field = '', $lang = false)
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $pickup_point_translation = $this->pickup_point_translations->where('lang', $lang)->first();

        return $pickup_point_translation != null ? $pickup_point_translation->$field : $this->$field;
    }

    /**
     * @return HasMany<PickupPointTranslation, $this>
     */
    public function pickup_point_translations(): HasMany
    {
        return $this->hasMany(PickupPointTranslation::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
