<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $symbol
 * @property-read string|null $code
 * @property-read string $exchange_rate
 * @property-read int $status
 */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'code' => $this->code,
            'exchange_rate' => (string) $this->exchange_rate,
            'active' => (bool) $this->status,
        ];
    }
}
