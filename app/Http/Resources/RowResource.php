<?php

namespace App\Http\Resources;

use App\Models\Cell;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Callers must supply `has_pallets` via `Row::withHasPallets()` or
 * `Row::loadHasPallets()`; this resource deliberately has no fallback, which
 * previously hid an exists query per row (an N+1 on the unpaginated
 * `GET /api/v1/rows/full`).
 *
 * @property-read int $id
 * @property-read string $letter
 * @property-read int $cells_count
 * @property-read int $flats_count
 * @property-read bool $has_pallets
 * @property-read Collection<int, Cell> $cells
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
            'has_pallets' => (bool) $this->resource->has_pallets,
            'cells' => $this->whenLoaded('cells', fn () => CellResource::collection($this->cells)),
        ];
    }
}
