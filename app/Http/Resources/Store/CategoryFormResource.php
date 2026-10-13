<?php

namespace App\Http\Resources\Store;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read int $level
 * @property-read int $order_level
 * @property-read int|null $parent_id
 * @property-read int $digital
 * @property-read int $featured
 * @property-read string|float $commision_rate
 * @property-read string|null $banner
 * @property-read string|null $icon
 * @property-read string|null $cover_image
 * @property-read string|null $slug
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 *
 * @mixin Category
 */
class CategoryFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name,
            'parent_id' => $this->parent_id ?? 0, 'order_level' => $this->order_level,
            'digital' => (bool) $this->digital,
            'banner' => $this->banner, 'icon' => $this->icon, 'cover_image' => $this->cover_image,
            'slug' => $this->slug, 'meta_title' => $this->meta_title, 'meta_description' => $this->meta_description,
            'commision_rate' => (string) $this->commision_rate,
            'filtering_attributes' => $this->whenLoaded('attributes', fn () => $this->attributes->modelKeys()),
        ];
    }
}
