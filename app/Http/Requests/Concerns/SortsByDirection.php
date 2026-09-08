<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared `sort_direction` validation for sortable listings. The sortable
 * column list (`sort_by`) stays per-request, since every listing sorts by a
 * different set of columns.
 */
trait SortsByDirection
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function sortDirectionRules(): array
    {
        return [
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
