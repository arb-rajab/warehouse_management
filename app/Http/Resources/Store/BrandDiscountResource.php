<?php

namespace App\Http\Resources\Store;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $logo_url
 * @property-read Brand $resource
 */
class BrandDiscountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->resource->getTranslation('name'), 'logo_url' => $this->logo_url];
    }
}
