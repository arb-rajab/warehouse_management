<?php

namespace App\Http\Resources\Store;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Currency $resource */
class CurrencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'symbol' => $this->resource->symbol, 'code' => $this->resource->code, 'exchange_rate' => (string) $this->resource->exchange_rate, 'status' => (bool) $this->resource->status];
    }
}
