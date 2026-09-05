<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared filter validation for the admin cell verification reports listing.
 */
trait FiltersCellVerificationReports
{
    use FiltersByProductIds;
    use FiltersByRowAndColumn;

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
            'cell_verification_round_id' => ['nullable', 'integer', 'exists:cell_verification_rounds,id'],
            'cell_id' => ['nullable', 'integer', 'exists:cells,id'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', Rule::exists(User::class, 'id')],
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
