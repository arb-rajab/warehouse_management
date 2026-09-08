<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByDateRange;
use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByRowAndColumn;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\SortsByDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter validation for the admin listing of one verification round's reports
 * (Admin\CellVerificationRoundController::show()) — the round itself is scoped
 * by the route, not a filter field here.
 */
class FilterCellVerificationReportsRequest extends FormRequest
{
    use FiltersByDateRange;
    use FiltersByProductIds;
    use FiltersByRowAndColumn;
    use FiltersPerPage;
    use NormalizesBooleanFilters;
    use SortsByDirection;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('is_correct');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productIdsFilterRules(),
            ...$this->rowAndColumnFilterRules(),
            'cell_id' => ['nullable', 'integer', 'exists:cells,id'],
            'is_correct' => ['nullable', 'boolean'],
            ...$this->dateRangeFilterRules(),
            ...$this->sortDirectionRules(),
            ...$this->perPageRules(),
        ];
    }
}
