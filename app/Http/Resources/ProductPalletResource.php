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
 * @property-read int|null $cell_id
 * @property-read CellState|null $state
 * @property-read Carbon|null $expiration_date
 * @property-read Carbon|null $created_at
 * @property-read CarbonImmutable|null $cell_entered_at
 * @property-read int $remaining_boxes
 * @property-read int $product_boxes_count
 * @property-read Cell|null $cell
 *
 * `warehouse` is present when the caller loaded the relations it reads
 * (`cell.row.warehouse`, or `Pallet::WITH_RECEIPT_WAREHOUSE` for an unplaced
 * pallet), as Api\V1\ProductController::pallets() does. An unplaced pallet
 * has `placed: false` and a null `state`, `location` and `is_active`.
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
            'placed' => $this->cell_id !== null,
            'location' => $this->whenLoaded('cell', fn () => $this->cell?->toLocationArray()),
            'is_active' => $this->whenLoaded('cell', fn () => $this->cell?->is_active),
            'warehouse' => $this->when(
                $this->resource->warehouseLoaded(),
                fn () => $this->resource->warehouse === null ? null : new WarehouseSummaryResource($this->resource->warehouse),
            ),
        ];
    }
}
