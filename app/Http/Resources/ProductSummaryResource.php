<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $ar_name
 * @property-read string|null $image_url
 * @property-read bool $published
 * @property-read int $boxes_count
 * @property-read int|null $minimum_pallets
 * @property-read int $pallets_count
 * @property-read int $full_cells_count
 * @property-read int $opened_cells_count
 * @property-read int $expired_cells_count
 * @property-read int $expiring_soon_count
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
            'ar_name' => $this->ar_name,
            'image_url' => $this->image_url,
            'active' => $this->published,
            'boxes_count' => $this->boxes_count,
            'minimum_pallets' => $this->minimum_pallets,
            'pallets_count' => (int) $this->pallets_count,
            'full_cells_count' => (int) $this->full_cells_count,
            'opened_cells_count' => (int) $this->opened_cells_count,
            'expired_cells_count' => (int) $this->expired_cells_count,
            'expiring_soon_count' => (int) $this->expiring_soon_count,
        ];
    }
}
