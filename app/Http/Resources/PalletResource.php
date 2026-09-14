<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read CellState $state
 * @property-read Carbon $expiration_date
 * @property-read int $remaining_boxes
 * @property-read Product $product
 * @property-read Cell $cell
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
            'state' => $this->state->value,
            'expiration_date' => $this->expiration_date->toDateString(),
            'cell_entered_at' => $this->whenLoaded('cellEnteredLog', fn () => $this->cell_entered_at?->toIso8601String()),
            'remaining_boxes' => $this->remaining_boxes,
            'boxes_depleted_message' => $this->remaining_boxes === 0 ? __('messages.pallet_boxes_depleted') : null,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'location' => $this->whenLoaded('cell', fn () => $this->cell->toLocationArray()),
        ];
    }
}
