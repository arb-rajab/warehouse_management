<?php

namespace App\Http\Requests\Concerns;

use App\Models\Brand;

trait ValidatesStoreBrand
{
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function brandRules(?Brand $brand = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'logo' => ['nullable', 'integer', $this->retainableImageUploadRule('logo', $brand?->logo)],
            'slug' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
