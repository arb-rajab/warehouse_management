<?php

namespace App\Http\Resources\Store;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read int $state_id
 * @property-read string $cost
 * @property-read int $status
 * @property-read State|null $state
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var City $city */
        $city = $this->resource;
        $translation = $city->relationLoaded('city_translations')
            ? $city->city_translations->firstWhere('lang', app()->getLocale())
            : null;

        return [
            'id' => $city->id,
            'name' => $translation instanceof CityTranslation ? $translation->name : $city->name,
            'state_id' => $city->state_id,
            'cost' => (string) $city->cost,
            'active' => (bool) $city->status,
            'state' => $this->whenLoaded('state', fn () => $city->state === null ? null : new StateResource($city->state)),
        ];
    }
}
