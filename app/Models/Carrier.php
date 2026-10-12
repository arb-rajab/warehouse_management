<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $transit_time
 * @property int|null $logo
 * @property int|bool $free_shipping
 * @property int|bool $status
 * @property-read Collection<int, CarrierRange> $carrier_ranges
 * @property-read Collection<int, CarrierRangePrice> $carrier_range_prices
 */
class Carrier extends Model
{
    /** @return HasMany<CarrierRange, $this> */
    public function carrier_ranges(): HasMany
    {
        return $this->hasMany(CarrierRange::class);
    }

    /** @return HasMany<CarrierRangePrice, $this> */
    public function carrier_range_prices(): HasMany
    {
        return $this->hasMany(CarrierRangePrice::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
