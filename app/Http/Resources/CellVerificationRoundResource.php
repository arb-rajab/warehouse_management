<?php

namespace App\Http\Resources;

use App\Models\CellVerificationReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read Carbon $created_at
 * @property-read Carbon|null $completed_at
 * @property-read int|null $reports_count
 * @property-read Collection<int, CellVerificationReport> $reports
 * @property-read User $user
 */
class CellVerificationRoundResource extends JsonResource
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
            'started_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'reports_count' => $this->whenCounted('reports'),
            'reports' => $this->whenLoaded('reports', fn () => CellVerificationReportResource::collection($this->reports)),
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
        ];
    }
}
