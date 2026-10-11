<?php

namespace App\Http\Requests\Concerns;

use App\Models\Page;
use Illuminate\Validation\Rule;

trait ValidatesWebsitePage
{
    use ValidatesStoreRole;
    use ValidatesWebsiteSettings;

    /** @return array<string, mixed> */
    protected function websitePageRules(?Page $page = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'meta_image' => $this->imageSettingRules(),
            ...$this->languageRules('required'),
        ];
    }
}
