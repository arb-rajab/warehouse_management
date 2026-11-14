<?php

namespace App\Http\Resources;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLogFlag;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * The `flagged`/`flags` keys are admin-only: they carry the rule-based
 * anti-fraud signal raised against the log's own actor, so they are withheld
 * from a non-admin (mobile app) viewer — see CellStatusLog::WITH_FLAG_DETAILS.
 *
 * A record correction (`note_changed`, `product_changed`) of a received pallet
 * that has no cell yet has a null `cell`, `from_state` and `to_state`; every
 * other entry names its cell and states. `batch_id` is shared by the entries
 * one bulk request wrote; `old_product`/`new_product` are set on
 * `product_changed`, `old_note`/`new_note` (the pallet's own note) on both
 * corrections, and `reason` on a product change made through the bulk
 * endpoint.
 *
 * @property-read int $id
 * @property-read CellLogAction $action
 * @property-read CellState|null $from_state
 * @property-read CellState|null $to_state
 * @property-read string|null $note
 * @property-read string|null $batch_id
 * @property-read string|null $reason
 * @property-read string|null $old_note
 * @property-read string|null $new_note
 * @property-read Cell|null $cell
 * @property-read Cell|null $relatedCell
 * @property-read Product|null $product
 * @property-read Product|null $oldProduct
 * @property-read int|null $pallet_id
 * @property-read int|null $boxes_count
 * @property-read Pallet|null $pallet
 * @property-read User $user
 * @property-read Carbon $created_at
 * @property-read Carbon|null $next_log_at
 * @property-read int $duration_seconds
 * @property-read bool $flagged
 * @property-read Collection<int, CellStatusLogFlag> $flags
 */
class CellStatusLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $viewerIsAdmin = $viewer?->isAdmin() ?? false;

        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'from_state' => $this->from_state?->value,
            'to_state' => $this->to_state?->value,
            'note' => $this->note,
            'boxes_count' => $this->boxes_count,
            'batch_id' => $this->batch_id,
            'reason' => $this->reason,
            'old_note' => $this->old_note,
            'new_note' => $this->new_note,
            'old_product' => $this->whenLoaded('oldProduct', fn (): ?array => $this->productSummary($this->oldProduct)),
            'new_product' => $this->whenLoaded('product', fn (): ?array => $this->action === CellLogAction::ProductChanged ? $this->productSummary($this->product) : null),
            'cell' => $this->whenLoaded('cell', fn () => $this->cell?->toLocationWithWarehouseArray()),
            'related_cell' => $this->whenLoaded('relatedCell', fn () => $this->relatedCell?->toLocationWithWarehouseArray()),
            'product' => $this->whenLoaded('product', fn () => $this->product === null ? null : new ProductResource($this->product)),
            'pallet' => $this->pallet_id === null ? null : [
                'id' => $this->pallet_id,
                'expiration_date' => $this->whenLoaded('pallet', fn () => $this->pallet?->expiration_date?->toDateString()),
            ],
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'created_at' => $this->created_at->toIso8601String(),
            'next_log_at' => $this->next_log_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'flagged' => $this->when($viewerIsAdmin, fn (): bool => (bool) $this->flagged),
            'flags' => $this->when(
                $viewerIsAdmin,
                fn () => $this->whenLoaded('flags', fn (): array => $this->flags->map(fn (CellStatusLogFlag $flag): array => [
                    'id' => $flag->id,
                    'reason' => $flag->reason->value,
                    'acknowledged' => $flag->acknowledged_at !== null,
                    'acknowledged_by' => $flag->relationLoaded('acknowledgedBy') && $flag->acknowledgedBy !== null
                        ? new UserSummaryResource($flag->acknowledgedBy)
                        : null,
                ])->all()),
            ),
        ];
    }

    /**
     * The id and names of a product a correction entry names.
     *
     * @return array{id: int, name: string, ar_name: string}|null
     */
    private function productSummary(?Product $product): ?array
    {
        return $product === null ? null : [
            'id' => $product->id,
            'name' => $product->name,
            'ar_name' => $product->ar_name,
        ];
    }
}
