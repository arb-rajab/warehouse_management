<?php

namespace App\Http\Resources\Store;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read CarbonInterface|null $created_at
 */
class OffersCatalogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
