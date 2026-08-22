<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $image_url
 * @property-read int $full_cells_count
 * @property-read int $opened_cells_count
 * @property-read int $expired_cells_count
 * @property-read int $expiring_soon_count
 * @property-read int $activity_today_count
 * @property-read int $activity_week_count
 */
class ProductSummaryResource extends JsonResource
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
            'name' => $this->name,
            'image_url' => $this->image_url,
            'full_cells_count' => (int) $this->full_cells_count,
            'opened_cells_count' => (int) $this->opened_cells_count,
            'expired_cells_count' => (int) $this->expired_cells_count,
            'expiring_soon_count' => (int) $this->expiring_soon_count,
            'activity_today_count' => (int) $this->activity_today_count,
            'activity_week_count' => (int) $this->activity_week_count,
        ];
    }
}
