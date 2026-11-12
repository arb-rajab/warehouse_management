<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use Illuminate\Http\Request;

/**
 * A pallet in the admin's pallet list (`GET pallets`) and in the answers of
 * the bulk corrections: the {@see PalletResource} fields plus the receipt it
 * came from (`{id, created_at}`, null for a pallet stored directly into a
 * cell) and its current ETag as `etag`, quoted exactly like the `ETag`
 * header, to send back as the pallet's `etag` (or `If-Match`).
 *
 * The caller loads `cell` (with `row.warehouse`), `product` and the receipt
 * with `created_at`, as Api\V1\PalletController::LIST_EAGER_LOAD does: the
 * ETag reads the cell's state.
 *
 * @property-read Receipt|null $receipt
 */
class PalletListResource extends PalletResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /** The receipt this pallet was received on; null when it was stored straight into a cell. */
            'receipt' => $this->receipt === null ? null : [
                'id' => $this->receipt->id,
                'created_at' => $this->receipt->created_at?->toIso8601String(),
            ],
            /** The pallet's current ETag, quoted as in the `ETag` header. */
            'etag' => '"'.$this->resource->etag().'"',
        ];
    }
}
