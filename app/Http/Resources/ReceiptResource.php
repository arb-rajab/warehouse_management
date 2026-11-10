<?php

namespace App\Http\Resources;

use App\Enums\ReceiptStatus;
use App\Models\ReceiptFile;
use App\Models\ReceiptPallet;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A truck receipt. The list carries the header and counts; the detail adds
 * `files` and `pallets` (Receipt::withDetails()). `pallets` are the pallets
 * as written on the bill by the merchant, in the order typed: never the id
 * of a pallet finalizing created (those are listed by `GET pallets/unplaced`).
 *
 * @property-read int $id
 * @property-read string|null $note
 * @property-read ReceiptStatus $status
 * @property-read CarbonImmutable|null $finalized_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read User|null $finalizedBy
 * @property-read Collection<int, ReceiptFile> $files
 * @property-read Collection<int, ReceiptPallet> $pallets
 */
class ReceiptResource extends JsonResource
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
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseSummaryResource($this->warehouse)),
            'note' => $this->note,
            'status' => $this->status->value,
            'pallets_count' => $this->whenCounted('pallets'),
            'files_count' => $this->whenCounted('files'),
            'created_by' => $this->whenLoaded('createdBy', fn () => new UserSummaryResource($this->createdBy)),
            'finalized_by' => $this->whenLoaded('finalizedBy', fn () => $this->finalizedBy !== null ? new UserSummaryResource($this->finalizedBy) : null),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'files' => $this->whenLoaded('files', fn () => ReceiptFileResource::collection($this->files)),
            'pallets' => $this->whenLoaded('pallets', fn () => $this->pallets->map(fn (ReceiptPallet $pallet): array => [
                'product_id' => $pallet->product_id,
                'product' => new ProductResource($pallet->product),
                'boxes' => $pallet->boxes,
                'expiration_date' => $pallet->expiration_date?->toDateString(),
            ])->all()),
        ];
    }
}
