<?php

namespace App\Http\Resources\Store;

use App\Models\CustomerProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $slug
 * @property-read string $added_by
 * @property-read int $status
 * @property-read int $published
 * @property-read string|null $thumbnail_url
 * @property-read CustomerProduct $resource
 */
class ClassifiedProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->getTranslation('name'),
            'public_url' => route('customer.product', $this->slug),
            'added_by' => $this->added_by,
            'status' => (bool) $this->status,
            'published' => (bool) $this->published,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }
}
