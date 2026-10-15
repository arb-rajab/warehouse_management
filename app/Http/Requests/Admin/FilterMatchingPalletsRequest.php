<?php

namespace App\Http\Requests\Admin;

use App\Enums\CellState;
use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\NormalizesExpiredFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The highlight-filter query params the dashboard's stat tiles and the
 * products page's occupancy counts link to the matching pallets list with.
 */
class FilterMatchingPalletsRequest extends FormRequest
{
    use FiltersByProductIds;
    use FiltersByStaleAfterDays;
    use FiltersPerPage;
    use NormalizesBooleanFilters;
    use NormalizesExpiredFilter;

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
            'state' => ['nullable', 'string', Rule::enum(CellState::class)],
            'is_active' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:1'],
            'expired' => ['nullable', 'boolean'],
            ...$this->staleAfterDaysFilterRules(),
            ...$this->productIdsFilterRules(),
            ...$this->perPageRules(),
        ];
    }
}
