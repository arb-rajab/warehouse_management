<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read CellState $state
 * @property-read Carbon|null $expiration_date
 * @property-read Carbon|null $created_at
 * @property-read CarbonImmutable|null $cell_entered_at
 * @property-read int $remaining_boxes
 * @property-read int $product_boxes_count
 * @property-read Cell $cell
 *
 * `warehouse` is present when the caller loaded `cell.row.warehouse`, as
 * Api\V1\ProductController::pallets() does.
 */
class ProductPalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toListingSummaryArray(),
            'cell_entered_at' => $this->whenLoaded('cellEnteredLog', fn () => $this->cell_entered_at?->toIso8601String()),
            'product_boxes_count' => $this->product_boxes_count,
            'location' => $this->whenLoaded('cell', fn () => $this->cell->toLocationArray()),
            'is_active' => $this->whenLoaded('cell', fn () => $this->cell->is_active),
            'warehouse' => $this->when(
                $this->resource->relationLoaded('cell') && $this->cell->relationLoaded('row') && $this->cell->row->relationLoaded('warehouse'),
                fn () => new WarehouseSummaryResource($this->cell->row->warehouse),
            ),
        ];
    }
}
