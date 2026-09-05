<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
