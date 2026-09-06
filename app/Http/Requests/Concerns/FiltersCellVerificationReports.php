<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared filter validation for the admin listing of one verification round's
 * reports (Admin\CellVerificationRoundController::show()) — the round itself
 * is scoped by the route, not a filter field here.
 */
trait FiltersCellVerificationReports
{
    use FiltersByProductIds;
    use FiltersByRowAndColumn;
    use NormalizesBooleanFilters;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('is_correct');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cellVerificationReportFilterRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function cellVerificationReportFilterRules(): array
    {
        return [
            ...$this->productIdsFilterRules(),
            ...$this->rowAndColumnFilterRules(),
            'cell_id' => ['nullable', 'integer', 'exists:cells,id'],
            'is_correct' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            // Alternative to date_from/date_to, not a companion to them — mutually
            // exclusive, same convention as FiltersCellStatusLogs::created_within_days.
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
