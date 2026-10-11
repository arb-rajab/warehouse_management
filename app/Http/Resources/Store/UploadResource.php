<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $path = $this->resource->getAttribute('file_name');
        $external = $this->resource->getAttribute('external_link');
        $url = $this->url;
        if (blank($external) && is_string($path) && preg_match('#^uploads/all/[^/\\\\]+$#D', $path) === 1) {
            if (is_file(public_path($path))) {
                $url = asset($path);
            } elseif (config('filesystems.default') === 's3') {
                $url = Storage::disk('s3')->url($path);
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->file_original_name,
            'type' => $this->type,
            'size' => $this->file_size,
            'url' => $url !== null && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null,
        ];
    }
}
