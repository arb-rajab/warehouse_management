<?php

namespace App\Http\Resources\Store;

use App\Models\PickupPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read PickupPoint $resource */
class PickupPointResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->pickup_point_translations->firstWhere('lang', app()->getLocale());

        return ['id' => $this->resource->id, 'name' => $translation->name ?? $this->resource->name, 'address' => $translation->address ?? $this->resource->address, 'phone' => $this->resource->phone, 'staff_id' => $this->resource->staff_id, 'staff' => $this->whenLoaded('staff', fn () => $this->resource->staff?->user === null ? null : ['id' => $this->resource->staff->id, 'name' => $this->resource->staff->user->name]), 'pick_up_status' => (bool) $this->resource->pick_up_status];
    }
}
