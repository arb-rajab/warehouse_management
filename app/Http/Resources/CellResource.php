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
 * @property-read bool $is_active
 * @property-read Row $row
 *
 * `warehouse` is present when the caller loaded `row.warehouse`
 * (`Cell::WITH_ROW_AND_CONTENTS`); cells nested in a row payload leave it to
 * the row.
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
            'warehouse' => $this->when(
                $this->resource->relationLoaded('row') && $this->row->relationLoaded('warehouse'),
                fn () => new WarehouseSummaryResource($this->row->warehouse),
            ),
            'cell_number' => $this->cell_number,
            'flat_number' => $this->flat_number,
            'state' => $this->state->value,
            'is_active' => $this->is_active,
            'pallet' => $this->when($palletLoaded, fn () => $pallet === null ? null : [
                'id' => $pallet->id,
                ...$pallet->toMapSummaryArray(),
                'is_stale' => $request->filled('stale_after_days')
                    ? $pallet->isStaleAfter($request->integer('stale_after_days'))
                    : null,
                'remaining_boxes' => $pallet->remaining_boxes,
                'product_boxes_count' => $this->when(
                    $pallet->product->relationLoaded('setting'),
                    fn (): int => $pallet->product->boxes_count,
                ),
            ]),
        ];
    }
}
