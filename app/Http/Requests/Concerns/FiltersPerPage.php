<?php

namespace App\Http\Requests\Concerns;

use App\Http\Controllers\Concerns\PerPageOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared `per_page` validation for admin table listings that already use a
 * FormRequest — restricts it to PerPageOptions::VALUES so an out-of-range
 * value 422s instead of silently falling back.
 */
trait FiltersPerPage
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function perPageRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', Rule::in(PerPageOptions::VALUES)],
        ];
    }
}
