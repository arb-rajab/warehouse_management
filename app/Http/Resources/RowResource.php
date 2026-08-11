<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $letter
 * @property-read int $cells_count
 * @property-read int $flats_count
 */
class RowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'letter' => $this->letter,
            'cells_count' => $this->cells_count,
            'flats_count' => $this->flats_count,
            'has_pallets' => $this->resource->hasPallets(),
        ];
    }
}
