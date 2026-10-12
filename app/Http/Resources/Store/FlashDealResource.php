<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $title
 * @property-read string $slug
 * @property-read int|null $banner
 * @property-read int|null $start_date
 * @property-read int|null $end_date
 * @property-read string|null $background_color
 * @property-read string|null $text_color
 * @property-read int|bool $status
 * @property-read int|bool $featured
 */
class FlashDealResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'slug' => $this->slug, 'banner' => $this->banner === null ? '' : (string) $this->banner, 'start_date' => $this->start_date ? date('Y-m-d\TH:i:s', $this->start_date) : '', 'end_date' => $this->end_date ? date('Y-m-d\TH:i:s', $this->end_date) : '', 'background_color' => $this->background_color, 'text_color' => $this->text_color, 'status' => (bool) $this->status, 'featured' => (bool) $this->featured, 'public_url' => route('flash-deal-details', $this->slug)];
    }
}
