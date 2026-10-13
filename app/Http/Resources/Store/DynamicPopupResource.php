<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $title
 * @property-read string|null $summary
 * @property-read string|null $banner
 * @property-read string|null $btn_text
 * @property-read int $delay_seconds
 * @property-read int|null $offer_id
 * @property-read int $show_page
 */
class DynamicPopupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'summary' => $this->summary,
            'banner' => (string) $this->banner, 'btn_text' => $this->btn_text,
            'delay_seconds' => (int) $this->delay_seconds, 'offer_id' => $this->offer_id === null ? null : (int) $this->offer_id,
            'show_page' => (int) $this->show_page,
        ];
    }
}
