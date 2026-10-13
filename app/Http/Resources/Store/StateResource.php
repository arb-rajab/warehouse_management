<?php

namespace App\Http\Resources\Store;

use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read State $resource */
class StateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'country_id' => $this->resource->country_id, 'country' => $this->whenLoaded('country', fn () => $this->resource->country === null ? null : ['id' => $this->resource->country->id, 'name' => $this->resource->country->name]), 'status' => (bool) $this->resource->status];
    }
}
