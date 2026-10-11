<?php

namespace App\Http\Resources\Store;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Zone $resource */
class ZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'status' => (bool) $this->resource->status];
    }
}
