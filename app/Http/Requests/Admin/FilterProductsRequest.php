<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByDateRange;
use App\Http\Requests\Concerns\FiltersByLogAction;
use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByRowAndColumn;
use App\Http\Requests\Concerns\FiltersByUserIds;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\NormalizesExpiredFilter;
use App\Http\Requests\Concerns\SortsByDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterProductsRequest extends FormRequest
{
    use FiltersByDateRange;
    use FiltersByLogAction;
    use FiltersByProductIds;
    use FiltersByRowAndColumn;
    use FiltersByUserIds;
    use FiltersPerPage;
    use NormalizesBooleanFilters;
    use NormalizesExpiredFilter;
    use SortsByDirection;

    protected function prepareForValidation(): void
    {
        $this->normalizeExpiredFilter();
        $this->normalizeBooleanFilter('inactive');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->rowAndColumnFilterRules(),
            // No `empty` option here (unlike the cells map) — an empty cell
            // never holds a product, so filtering a per-product listing to
            // it would always zero out every column.
            'state' => ['nullable', 'string', 'in:full,opened'],
            'expired' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:1'],
            'inactive' => ['nullable', 'boolean'],
            ...$this->productIdsFilterRules(),
            ...$this->userIdsFilterRules(),
            ...$this->logActionFilterRules(),
            ...$this->dateRangeFilterRules(),
            'sort_by' => ['nullable', 'in:name,full_cells_count,opened_cells_count,expired_cells_count,expiring_soon_count,activity_today_count,activity_week_count'],
            ...$this->sortDirectionRules(),
            ...$this->perPageRules(),
        ];
    }
}
