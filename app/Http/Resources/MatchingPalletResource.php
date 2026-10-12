<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Product;
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
 * @property-read Cell $cell
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
        return [
            ...$this->resource->toListingSummaryArray(),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'location' => $this->whenLoaded('cell', fn () => $this->cell->toLocationArray()),
        ];
    }
}
