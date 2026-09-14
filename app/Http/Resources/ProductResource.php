<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $ar_name
 * @property-read string|null $image_url
 * @property-read int $boxes_count
 * @property-read bool $published
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ar_name' => $this->ar_name,
            'image_url' => $this->image_url,
            'boxes_count' => $this->boxes_count,
            'active' => $this->published,
        ];
    }
}
