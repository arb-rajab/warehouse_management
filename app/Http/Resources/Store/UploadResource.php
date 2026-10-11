<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $file_original_name
 * @property-read string|null $type
 * @property-read int|null $file_size
 * @property-read string|null $url
 */
class UploadResource extends JsonResource
{
    /** @return array{id: int, name: string|null, type: string|null, size: int|null, url: string|null} */
    public function toArray(Request $request): array
    {
        $url = $this->url;

        return [
            'id' => $this->id,
            'name' => $this->file_original_name,
            'type' => $this->type,
            'size' => $this->file_size,
            'url' => $url !== null && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null,
        ];
    }
}
