<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\ProductFieldSource;
use App\Services\ProductFieldSourceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Setup > Product data sources: one owner per Otajer-synced product field.
 * `sources` may name only the registered syncable fields, each exactly once
 * (it is a keyed array), and every one of them is required with a single
 * value, `otajer` or `admin`, so a field can never have both owners.
 */
class UpdateProductFieldSourcesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fields = app(ProductFieldSourceService::class)->fields();
        $rules = ['sources' => ['required', 'array:'.implode(',', $fields)]];
        foreach ($fields as $field) {
            $rules['sources.'.$field] = ['required', 'string', Rule::enum(ProductFieldSource::class)];
        }

        return $rules;
    }

    /** @return array<string, ProductFieldSource> */
    public function sources(): array
    {
        /** @var array<string, string> $sources */
        $sources = $this->validated('sources');

        return array_map(ProductFieldSource::from(...), $sources);
    }
}
