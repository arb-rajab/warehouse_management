<?php

namespace App\Http\Resources\Store;

use App\Models\PickupPoint;
use App\Models\PickupPointTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $staff_id
 * @property-read string $name
 * @property-read string $address
 * @property-read string $phone
 * @property-read int|null $pick_up_status
 */
class PickupPointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PickupPoint $pickupPoint */
        $pickupPoint = $this->resource;
        $translation = $pickupPoint->relationLoaded('pickup_point_translations')
            ? $pickupPoint->pickup_point_translations->firstWhere('lang', app()->getLocale())
            : null;

        return [
            'id' => $pickupPoint->id,
            'staff_id' => $pickupPoint->staff_id,
            'name' => $translation instanceof PickupPointTranslation ? $translation->name : $pickupPoint->name,
            'address' => $translation instanceof PickupPointTranslation ? $translation->address : $pickupPoint->address,
            'phone' => $pickupPoint->phone,
            'active' => (bool) $pickupPoint->pick_up_status,
            'manager' => $this->whenLoaded('staff', fn () => $pickupPoint->staff?->user === null ? null : [
                'id' => $pickupPoint->staff->id,
                'name' => $pickupPoint->staff->user->name,
            ]),
        ];
    }
}
