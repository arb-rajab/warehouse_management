<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByDateRange;
use App\Http\Requests\Concerns\FiltersByUserIds;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\SortsByDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellVerificationRoundsRequest extends FormRequest
{
    use FiltersByDateRange;
    use FiltersByUserIds;
    use FiltersPerPage;
    use NormalizesBooleanFilters;
    use SortsByDirection;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('completed');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->userIdsFilterRules(),
            'completed' => ['nullable', 'boolean'],
            ...$this->dateRangeFilterRules(),
            ...$this->sortDirectionRules(),
            ...$this->perPageRules(),
        ];
    }
}
