<?php

namespace App\Http\Requests\Admin;

use App\Enums\CellLogAction;
use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByRowAndColumn;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesExpiredFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterProductsRequest extends FormRequest
{
    use FiltersByProductIds, FiltersByRowAndColumn, FiltersPerPage, NormalizesExpiredFilter;

    protected function prepareForValidation(): void
    {
        $this->normalizeExpiredFilter();
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
            ...$this->productIdsFilterRules(),
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', 'exists:users,id'],
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(CellLogAction::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
            'sort_by' => ['nullable', 'in:name,full_cells_count,opened_cells_count,expired_cells_count,expiring_soon_count,activity_today_count,activity_week_count'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            ...$this->perPageRules(),
        ];
    }
}
