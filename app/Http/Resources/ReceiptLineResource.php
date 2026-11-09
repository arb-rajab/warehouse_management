<?php

namespace App\Http\Resources;

use App\Enums\ReceiptLineStatus;
use App\Models\Product;
use App\Models\ReceiptLinePallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line of a receipt: what the bill expects (`expected_*`, null on an extra
 * line) and the pallets that actually arrived. `pallets[].pallet_id` is the
 * pallet finalizing created (null while the receipt is a draft, or once that
 * pallet has been deleted).
 *
 * @property-read int $id
 * @property-read int $product_id
 * @property-read bool $is_extra
 * @property-read int|null $expected_pallets_count
 * @property-read int|null $expected_boxes
 * @property-read CarbonImmutable|null $expected_expiration_date
 * @property-read ReceiptLineStatus $status
 * @property-read string|null $note
 * @property-read Product $product
 * @property-read Collection<int, ReceiptLinePallet> $actualPallets
 */
class ReceiptLineResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'is_extra' => $this->is_extra,
            'expected_pallets_count' => $this->expected_pallets_count,
            /** Boxes per pallet on the bill. */
            'expected_boxes' => $this->expected_boxes,
            'expected_expiration_date' => $this->expected_expiration_date?->toDateString(),
            'status' => $this->status->value,
            'note' => $this->note,
            'pallets' => $this->whenLoaded('actualPallets', fn () => $this->actualPallets->map(fn (ReceiptLinePallet $pallet): array => [
                'id' => $pallet->id,
                'boxes' => $pallet->boxes,
                'expiration_date' => $pallet->expiration_date?->toDateString(),
                'pallet_id' => $pallet->pallet_id,
            ])->all()),
        ];
    }
}
