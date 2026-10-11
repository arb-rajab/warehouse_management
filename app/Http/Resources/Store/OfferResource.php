<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $title
 * @property-read string $offer_type
 * @property-read int|null $banner
 * @property-read int|null $brand_id
 * @property-read int|null $start_date
 * @property-read int|null $end_date
 * @property-read int|bool $is_active
 * @property-read int|bool $is_repeatable
 * @property-read int|bool $multiple_usage
 */
class OfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'offer_type' => $this->offer_type, 'banner' => $this->banner === null ? '' : (string) $this->banner, 'brand_id' => $this->brand_id, 'start_date' => $this->start_date ? date('Y-m-d\TH:i:s', $this->start_date) : '', 'end_date' => $this->end_date ? date('Y-m-d\TH:i:s', $this->end_date) : '', 'is_active' => (bool) $this->is_active, 'is_repeatable' => (bool) $this->is_repeatable, 'multiple_usage' => (bool) $this->multiple_usage];
    }
}
