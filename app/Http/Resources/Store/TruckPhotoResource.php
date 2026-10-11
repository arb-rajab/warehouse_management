<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $file_original_name
 * @property-read string|null $file_name
 * @property-read string|null $external_link
 */
class TruckPhotoResource extends JsonResource
{
    /** @return array{id: int, name: string, url: string|null} */
    public function toArray(Request $request): array
    {
        $url = filled($this->external_link) ? $this->external_link : (filled($this->file_name) ? my_asset($this->file_name) : null);

        return [
            'id' => $this->id,
            'name' => $this->file_original_name ?? (string) $this->id,
            'url' => $url !== null && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null,
        ];
    }
}
