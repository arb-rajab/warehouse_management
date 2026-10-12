<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $title
 * @property-read int|null $banner_upload_id
 * @property-read string|null $banner_file_name
 * @property-read string|null $banner_external_link
 * @property-read string|null $btn_link
 * @property-read int $status
 */
class DynamicPopupSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'banner_url' => $this->banner_upload_id === null ? static_asset('assets/img/placeholder.jpg')
                : ($this->banner_external_link === null ? my_asset($this->banner_file_name) : ($this->banner_file_name ?? $this->banner_external_link)),
            'btn_link' => $this->btn_link, 'status' => (bool) $this->status,
        ];
    }
}
