<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $mat_id
 * @property-read Product $resource
 */
class ProductSearchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->getTranslation('name'),
            'mat_id' => $this->mat_id,
        ];
    }
}
