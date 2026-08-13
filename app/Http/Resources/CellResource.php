<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Pallet;
use App\Models\Row;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $cell_number
 * @property-read int $flat_number
 * @property-read CellState $state
 * @property-read Row $row
 * @property-read Pallet|null $pallet
 */
class CellResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $palletLoaded = $this->resource->relationLoaded('pallet');
        $pallet = $palletLoaded ? $this->pallet : null;

        return [
            'id' => $this->id,
            'row_letter' => $this->whenLoaded('row', fn () => $this->row->letter),
            'cell_number' => $this->cell_number,
            'flat_number' => $this->flat_number,
            'state' => $this->state->value,
            'pallet' => $this->when($palletLoaded, fn () => $pallet === null ? null : [
                'id' => $pallet->id,
                'product_name' => $pallet->product->name,
                'product_image_url' => $pallet->product->image_url,
                'expiration_date' => $pallet->expiration_date->toDateString(),
                'added_at' => $pallet->created_at?->toIso8601String(),
                'is_stale' => $pallet->is_stale,
            ]),
        ];
    }
}
