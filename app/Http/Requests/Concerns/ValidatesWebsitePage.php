<?php

namespace App\Http\Requests\Concerns;

use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @mixin FormRequest */
trait ValidatesWebsitePage
{
    use ValidatesStoreUploadReference;

    /**
     * The `pages` and `page_translations` widths: the title is VARCHAR(255)
     * (NOT NULL in the translations), the slug VARCHAR(255), the meta title
     * TEXT, the meta description and keywords VARCHAR(1000), and the meta
     * image VARCHAR(255) holding an upload id. The content is LONGTEXT, wider
     * than any request the server accepts, so it has no length rule.
     *
     * Only a custom page has an editable slug, unique among all pages; the
     * policy pages keep theirs. An unchanged meta image may round-trip
     * without the owner check (retainableImageUploadRule()).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function websitePageRules(?Page $page): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $page === null || $page->type === 'custom_page'
                ? ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('pages', 'slug')->ignore($page?->id)]
                : ['prohibited'],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'meta_image' => ['nullable', 'integer', $this->retainableImageUploadRule('meta_image', $page?->meta_image)],
        ];
    }

    /**
     * Normalises the slug as the Blade writer did (spaces to dashes, then
     * anything but letters, digits and dashes dropped) and cleans the content
     * with App\Services\HtmlSanitizer, so what is validated is what is stored.
     */
    protected function prepareWebsitePage(): void
    {
        $prepared = [];
        $slug = $this->input('slug');
        if (is_string($slug)) {
            $prepared['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $slug));
        }
        $content = $this->input('content');
        if (is_string($content)) {
            $prepared['content'] = clean_html($content);
        }
        $this->merge($prepared);
    }
}
