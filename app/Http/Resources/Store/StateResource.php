<?php

namespace App\Http\Resources\Store;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read int $country_id
 * @property-read int $status
 * @property-read Country|null $country
 */
class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_id' => $this->country_id,
            'active' => (bool) $this->status,
            'country' => $this->whenLoaded('country', fn () => $this->country === null ? null : new CountryResource($this->country)),
        ];
    }
}
