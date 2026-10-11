<?php

namespace App\Http\Resources\Store;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Page $resource */
class WebsitePageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            'id' => $page->id, 'title' => $page->page_translations->firstWhere('lang', app()->getLocale())->title ?? $page->title,
            'slug' => $page->slug, 'type' => $page->type,
            'public_url' => $page->type === 'home_page' ? route('home') : route('custom-pages.show_custom_page', $page->slug),
        ];
    }
}
