<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $carrier_id
 * @property string $billing_type
 * @property string $delimiter1
 * @property string $delimiter2
 * @property-read Carrier $carrier
 * @property-read Collection<int, CarrierRangePrice> $carrier_range_prices
 */
class CarrierRange extends Model
{
    /** @return BelongsTo<Carrier, $this> */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /** @return HasMany<CarrierRangePrice, $this> */
    public function carrier_range_prices(): HasMany
    {
        return $this->hasMany(CarrierRangePrice::class);
    }
}
