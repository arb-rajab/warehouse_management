<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\NormalizesExpiredFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The same highlight-filter shape ShowCellMapRequest validates (minus its
 * map-navigation-only `flat_number`/`search` fields) — this list and the
 * cell map must accept and mean exactly the same filter query params so a
 * dashboard tile's query works unchanged on either page, and so the list
 * page's "view on map" button can hand its own query straight to the map.
 */
class ShowMatchingPalletsRequest extends FormRequest
{
    use FiltersByProductIds, FiltersByStaleAfterDays, FiltersPerPage, NormalizesBooleanFilters, NormalizesExpiredFilter;

    protected function prepareForValidation(): void
    {
        $this->normalizeExpiredFilter();
        $this->normalizeBooleanFilter('is_active');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['nullable', 'string', 'in:empty,full,opened'],
            'is_active' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:1'],
            'expired' => ['nullable', 'boolean'],
            ...$this->staleAfterDaysFilterRules(),
            ...$this->productIdsFilterRules(),
            ...$this->perPageRules(),
        ];
    }
}
