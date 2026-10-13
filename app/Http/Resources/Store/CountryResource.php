<?php

namespace App\Http\Resources\Store;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Country $resource */
class CountryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'code' => $this->resource->code, 'status' => (bool) $this->resource->status];
    }
}
