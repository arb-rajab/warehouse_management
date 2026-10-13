<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $attribute_id
 * @property-read string $value
 */
class AttributeValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
        ];
    }
}
