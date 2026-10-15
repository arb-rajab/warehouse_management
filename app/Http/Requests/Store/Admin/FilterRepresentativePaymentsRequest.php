<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterRepresentativePaymentsRequest extends FormRequest
{
    use FiltersPerPage;
    use NormalizesBooleanFilters;

    protected function prepareForValidation(): void
    {
        foreach (['accepted', 'rejected', 'pending'] as $status) {
            $this->normalizeBooleanFilter($status);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            'search' => ['nullable', 'string', 'max:255'],
            'accepted' => ['nullable', 'boolean'],
            'rejected' => ['nullable', 'boolean'],
            'pending' => ['nullable', 'boolean'],
        ];
    }
}
