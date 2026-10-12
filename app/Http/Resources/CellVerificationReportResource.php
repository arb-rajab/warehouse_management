<?php

namespace App\Http\Resources;

use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $cell_verification_round_id
 * @property-read CellVerificationRound $round
 * @property-read bool $is_correct
 * @property-read Cell $cell
 * @property-read CellState $expected_cell_state
 * @property-read Product|null $expectedProduct
 * @property-read int|null $expected_boxes_count
 * @property-read Carbon|null $expected_expiration_date
 * @property-read CellState|null $reported_cell_state
 * @property-read Product|null $reportedProduct
 * @property-read int|null $reported_boxes_count
 * @property-read Carbon|null $reported_expiration_date
 * @property-read string|null $note
 * @property-read User $user
 * @property-read Carbon|null $accepted_at
 * @property-read User|null $acceptedBy
 * @property-read Carbon $created_at
 */
class CellVerificationReportResource extends JsonResource
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
            'cell_verification_round_id' => $this->cell_verification_round_id,
            'is_correct' => (bool) $this->is_correct,
            'cell' => $this->whenLoaded('cell', fn () => $this->cell->toLocationArray()),
            'expected' => [
                'cell_state' => $this->expected_cell_state->value,
                'product' => $this->whenLoaded('expectedProduct', fn () => $this->expectedProduct === null ? null : new ProductResource($this->expectedProduct)),
                'boxes_count' => $this->expected_boxes_count,
                'expiration_date' => $this->expected_expiration_date?->toDateString(),
            ],
            'reported' => [
                'cell_state' => $this->reported_cell_state?->value,
                'product' => $this->whenLoaded('reportedProduct', fn () => $this->reportedProduct === null ? null : new ProductResource($this->reportedProduct)),
                'boxes_count' => $this->reported_boxes_count,
                'expiration_date' => $this->reported_expiration_date?->toDateString(),
            ],
            'note' => $this->note,
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'round_completed' => $this->whenLoaded('round', fn () => $this->round->completed_at !== null),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'accepted_by' => $this->whenLoaded('acceptedBy', fn () => $this->acceptedBy === null ? null : new UserSummaryResource($this->acceptedBy)),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
