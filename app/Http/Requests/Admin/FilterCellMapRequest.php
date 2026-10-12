<?php

namespace App\Http\Requests\Admin;

use App\Enums\CellState;
use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Http\Requests\Concerns\NormalizesExpiredFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCellMapRequest extends FormRequest
{
    use FiltersByProductIds;
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
            'flat_number' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', 'string', Rule::enum(CellState::class)],
            'is_active' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:1'],
            'stale_after_days' => ['nullable', 'integer', 'min:1'],
            'expired' => ['nullable', 'boolean'],
            ...$this->productIdsFilterRules(),
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
