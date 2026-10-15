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
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name'),
            'parent' => $this->whenLoaded('parentCategory', fn () => $this->parentCategory ? ['id' => $this->parentCategory->id, 'name' => $this->parentCategory->getTranslation('name')] : null),
            'order_level' => $this->order_level,
            'level' => $this->level,
            'banner_url' => $this->banner === null ? null : uploaded_asset($this->banner),
            'icon_url' => $this->icon === null ? null : uploaded_asset($this->icon),
            'cover_image_url' => $this->cover_image === null ? null : uploaded_asset($this->cover_image),
            'featured' => (bool) $this->featured,
            'commision_rate' => (string) $this->commision_rate,
        ];
    }
}
