<?php

namespace App\Http\Resources\Store;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Tax $resource */
class TaxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'tax_status' => (bool) $this->resource->tax_status];
    }
}
