<?php

namespace App\Http\Resources\Store;

use App\Models\CarrierRange;
use App\Models\CarrierRangePrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $transit_time
 * @property-read int|null $logo
 * @property-read bool $free_shipping
 * @property-read bool $status
 * @property-read Collection<int, CarrierRange> $carrier_ranges
 */
class CarrierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'transit_time' => $this->transit_time,
            'logo' => $this->logo === null ? null : (int) $this->logo,
            'free_shipping' => (bool) $this->free_shipping,
            'status' => (bool) $this->status,
            'ranges' => $this->whenLoaded('carrier_ranges', fn () => $this->carrier_ranges->map(fn (CarrierRange $range) => [
                'billing_type' => $range->billing_type,
                'delimiter1' => (string) $range->delimiter1,
                'delimiter2' => (string) $range->delimiter2,
                'prices' => (object) $range->carrier_range_prices->mapWithKeys(fn (CarrierRangePrice $price) => [(string) $price->zone_id => (string) $price->price])->all(),
            ])->all()),
        ];
    }
}
