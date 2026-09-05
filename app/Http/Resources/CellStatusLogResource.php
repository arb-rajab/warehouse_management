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
 * @property-read int $id
 * @property-read CellLogAction $action
 * @property-read CellState $from_state
 * @property-read CellState $to_state
 * @property-read string|null $note
 * @property-read Cell $cell
 * @property-read Cell|null $relatedCell
 * @property-read Product|null $product
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
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'from_state' => $this->from_state->value,
            'to_state' => $this->to_state->value,
            'note' => $this->note,
            'boxes_count' => $this->boxes_count,
            'cell' => $this->whenLoaded('cell', fn () => $this->cell->toLocationArray()),
            'related_cell' => $this->whenLoaded('relatedCell', fn () => $this->relatedCell?->toLocationArray()),
            'product' => $this->whenLoaded('product', fn () => $this->product === null ? null : new ProductResource($this->product)),
            'pallet' => $this->pallet_id === null ? null : [
                'id' => $this->pallet_id,
                'expiration_date' => $this->whenLoaded('pallet', fn () => $this->pallet?->expiration_date?->toDateString()),
            ],
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
            'next_log_at' => $this->next_log_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'flagged' => (bool) $this->flagged,
            'flags' => $this->whenLoaded('flags', fn () => $this->flags->map(fn (CellStatusLogFlag $flag): array => [
                'id' => $flag->id,
                'reason' => $flag->reason->value,
                'acknowledged' => $flag->acknowledged_at !== null,
                'acknowledged_by' => $flag->relationLoaded('acknowledgedBy') && $flag->acknowledgedBy !== null
                    ? ['id' => $flag->acknowledgedBy->id, 'name' => $flag->acknowledgedBy->name]
                    : null,
            ])->all()),
        ];
    }
}
