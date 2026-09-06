<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCellVerificationRoundsRequest extends FormRequest
{
    use FiltersPerPage, NormalizesBooleanFilters;

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
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', Rule::exists(User::class, 'id')],
            'completed' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            // Alternative to date_from/date_to, not a companion to them — mutually
            // exclusive, same convention as FiltersCellStatusLogs::created_within_days.
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            ...$this->perPageRules(),
        ];
    }
}
