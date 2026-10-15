<?php

namespace App\Http\Resources\Store;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Attribute $resource
 */
class AttributeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->getTranslation('name'),
            'values' => $this->whenLoaded('attribute_values', fn () => AttributeValueResource::collection($this->resource->attribute_values)),
        ];
    }
}
