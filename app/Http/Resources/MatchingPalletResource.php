<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Product;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * The "matching pallets" admin list's row shape. Shares its `id`/`state`/
 * `expiration_date`/`added_at`/`remaining_boxes` fields with
 * `ProductPalletResource` via `Pallet::toListingSummaryArray()`, but isn't
 * built from either existing pallet resource directly: `PalletResource` (the
 * mobile API's pallet payload) and `ProductPalletResource` (a product's own
 * pallet history) both carry mobile-only concerns
 * (`boxes_depleted_message`, `product_boxes_count`) this admin-web list has
 * no use for, and this is the one place that needs the full product detail
 * alongside the pallet fields (the list spans every product, not one).
 *
 * @property-read int $id
 * @property-read CellState $state
 * @property-read Carbon|null $expiration_date
 * @property-read Carbon|null $created_at
 * @property-read int $remaining_boxes
 * @property-read Product $product
 * @property-read int|null $cell_id
 * @property-read int|null $receipt_id
 * @property-read Cell|null $cell
 * @property-read Receipt|null $receipt
 *
 * An unplaced pallet (received, no cell yet) has null `state`, `location` and
 * `cell_active`; it names the `warehouse` of its receipt and the `receipt_id`
 * the list links to instead. `warehouse` is null for a placed pallet, whose
 * `location` already carries it.
 */
class MatchingPalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $placed = $this->cell_id !== null;

        return [
            ...$this->resource->toListingSummaryArray(),
            'placed' => $placed,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'location' => $placed ? $this->whenLoaded('cell', fn () => $this->cell?->toLocationWithWarehouseArray()) : null,
            'cell_active' => $placed ? $this->whenLoaded('cell', fn () => $this->cell?->is_active) : null,
            'receipt_id' => $this->receipt_id,
            'warehouse' => $placed ? null : $this->whenLoaded('receipt', fn () => $this->receipt?->warehouse?->only(['id', 'name'])),
        ];
    }
}
