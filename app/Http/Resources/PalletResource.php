<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int|null $cell_id
 * @property-read CellState|null $state
 * @property-read Carbon|null $expiration_date
 * @property-read CarbonImmutable|null $cell_entered_at
 * @property-read int $remaining_boxes
 * @property-read Product $product
 * @property-read Cell|null $cell
 *
 * `warehouse` is present when the caller loaded the relations it reads
 * (`cell.row.warehouse`, or `Pallet::WITH_RECEIPT_WAREHOUSE` for an unplaced
 * pallet), as Api\V1\PalletController does. An unplaced pallet (received,
 * no cell yet) has `placed: false` and a null `state` and `location`; a
 * placed one keeps the shape it always had, plus `placed: true`.
 */
class PalletResource extends JsonResource
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
            'placed' => $this->cell_id !== null,
            'state' => $this->state?->value,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'cell_entered_at' => $this->whenLoaded('cellEnteredLog', fn () => $this->cell_entered_at?->toIso8601String()),
            'remaining_boxes' => $this->remaining_boxes,
            'boxes_depleted_message' => $this->remaining_boxes === 0 ? __('messages.pallet_boxes_depleted') : null,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'location' => $this->whenLoaded('cell', fn () => $this->cell?->toLocationArray()),
            'warehouse' => $this->when(
                $this->resource->warehouseLoaded(),
                fn () => $this->resource->warehouse === null ? null : new WarehouseSummaryResource($this->resource->warehouse),
            ),
        ];
    }

    /**
     * Hands the client the ETag to send back as `If-Match` on its next pallet action.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->cell_id === null || $this->resource->relationLoaded('cell')) {
            $response->setEtag($this->resource->etag());
        }
    }
}
