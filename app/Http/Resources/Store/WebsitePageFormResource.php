<?php

namespace App\Http\Resources\Store;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The language-independent fields of a website page's edit form; the title
 * and content of the language being edited are sent beside it.
 *
 * @property-read int $id
 * @property-read string $type
 * @property-read string|null $slug
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 * @property-read string|null $keywords
 * @property-read string|null $meta_image
 *
 * @mixin Page
 */
class WebsitePageFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'slug' => $this->slug ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_description' => $this->meta_description ?? '',
            'keywords' => $this->keywords ?? '',
            'meta_image' => (string) ($this->meta_image ?? ''),
            'public_url' => $this->publicUrl(),
        ];
    }
}
