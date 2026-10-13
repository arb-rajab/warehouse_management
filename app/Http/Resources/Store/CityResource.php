<?php

namespace App\Http\Resources\Store;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read City $resource */
class CityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->city_translations->firstWhere('lang', app()->getLocale());

        return ['id' => $this->resource->id, 'name' => $translation->name ?? $this->resource->name, 'state_id' => $this->resource->state_id, 'state' => $this->whenLoaded('state', fn () => $this->resource->state === null ? null : ['id' => $this->resource->state->id, 'name' => $this->resource->state->name]), 'cost' => (string) $this->resource->cost, 'status' => (bool) $this->resource->status];
    }
}
