<?php

namespace App\Http\Resources\Store;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $category_id
 * @property-read string $title
 * @property-read string $slug
 * @property-read string|null $short_description
 * @property-read int $status
 * @property-read BlogCategory|null $category
 * @property-read string|null $description
 * @property-read int|null $banner
 * @property-read string|null $meta_title
 * @property-read int|null $meta_img
 * @property-read string|null $meta_description
 * @property-read string|null $meta_keywords
 */
class BlogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->whenHas('description', fn () => $this->slug),
            'short_description' => $this->whenHas('short_description', fn () => $this->short_description),
            'status' => (bool) $this->status,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id, 'category_name' => $this->category->category_name,
            ]),
            'public_url' => route('blog.details', ['slug' => $this->slug]),
            'category_id' => $this->whenHas('description', fn () => $this->category_id),
            'description' => $this->whenHas('description', fn () => clean_html($this->description)),
            'banner' => $this->whenHas('banner', fn () => $this->banner === null ? '' : (string) $this->banner),
            'meta_title' => $this->whenHas('meta_title', fn () => $this->meta_title ?? ''),
            'meta_img' => $this->whenHas('meta_img', fn () => $this->meta_img === null ? '' : (string) $this->meta_img),
            'meta_description' => $this->whenHas('meta_description', fn () => $this->meta_description ?? ''),
            'meta_keywords' => $this->whenHas('meta_keywords', fn () => $this->meta_keywords ?? ''),
        ];
    }
}
