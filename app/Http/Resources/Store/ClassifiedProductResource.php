<?php

namespace App\Http\Resources\Store;

use App\Models\CustomerProduct;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $name
 * @property-read string|null $slug
 * @property-read string|null $added_by
 * @property-read int $status
 * @property-read int $published
 * @property-read Upload|null $thumbnailUpload
 *
 * @mixin CustomerProduct
 */
class ClassifiedProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $upload = $this->thumbnailUpload;

        return [
            'id' => $this->id,
            'name' => $this->resource->getTranslation('name'),
            'public_url' => route('customer.product', $this->slug),
            'thumbnail_url' => $upload === null ? static_asset('assets/img/placeholder.jpg')
                : ($upload->external_link == null ? my_asset($upload->file_name) : ($upload->file_name ?? $upload->external_link)),
            'added_by' => $this->added_by,
            'status' => (int) $this->status === 1,
            'published' => (int) $this->published === 1,
        ];
    }
}
