<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $carrier_id
 * @property int $carrier_range_id
 * @property int $zone_id
 * @property string $price
 * @property-read Carrier $carrier
 * @property-read CarrierRange $carrier_ranges
 * @property-read Zone $zone
 */
class CarrierRangePrice extends Model
{
    /** @return BelongsTo<Carrier, $this> */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /** @return BelongsTo<CarrierRange, $this> */
    public function carrier_ranges(): BelongsTo
    {
        return $this->belongsTo(CarrierRange::class, 'carrier_range_id');
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
