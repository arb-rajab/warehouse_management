<?php

namespace App\Http\Requests\Concerns;

use App\Models\Blog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreBlog
{
    use ValidatesStoreUploadReference;

    /**
     * The `blogs` widths: title, slug and meta title are VARCHAR(255), the
     * summary and meta texts TEXT. The body is LONGTEXT, wider than any
     * request the server accepts, so it has no length rule; it is cleaned by
     * App\Services\HtmlSanitizer before it is stored.
     *
     * An unchanged banner or meta image may round-trip without the owner check
     * (retainableImageUploadRule()); any other reference must pass it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function blogRules(?Blog $blog): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('blog_categories', 'id')->whereNull('deleted_at')],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('blogs', 'slug')->whereNull('deleted_at')->ignore($blog)],
            'short_description' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'integer', $this->retainableImageUploadRule('banner', $blog?->banner)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_img' => ['nullable', 'integer', $this->retainableImageUploadRule('meta_img', $blog?->meta_img)],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
