<?php

namespace App\Http\Resources\Store;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the website page list: the title in the back office's language
 * (from the eager-loaded translations), falling back to the base title, as
 * the Blade list's getTranslation('title') did.
 *
 * @property-read int $id
 * @property-read string $type
 * @property-read string|null $title
 * @property-read string|null $slug
 * @property-read Collection<int, PageTranslation> $page_translations
 *
 * @mixin Page
 */
class WebsitePageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->page_translations->firstWhere('lang', app()->getLocale())->title ?? $this->title ?? '',
            'slug' => $this->slug,
            'type' => $this->type,
            'public_url' => $this->publicUrl(),
        ];
    }
}
