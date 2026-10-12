<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersByDateRange;
use App\Http\Requests\Concerns\FiltersPerPage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class FilterReportsRequest extends FormRequest
{
    use FiltersByDateRange;
    use FiltersPerPage;

    protected function prepareForValidation(): void
    {
        foreach (['category_id' => 'category_ids', 'seller_id' => 'seller_ids', 'user_id' => 'user_ids', 'verification_status' => 'verification_statuses'] as $legacy => $multiple) {
            if ($this->filled($legacy) && ! $this->has($multiple)) {
                $this->merge([$multiple => [$this->input($legacy)]]);
            }
        }
        if ($this->filled('date_range') && is_string($this->input('date_range'))) {
            $bounds = explode(' / ', $this->input('date_range'));
            if (count($bounds) === 2 && ! $this->filled('date_from') && ! $this->filled('date_to')) {
                $this->merge(['date_from' => $bounds[0], 'date_to' => $bounds[1]]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * Historical account ids remain filterable after an account is deleted.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            'category_id' => ['nullable', 'integer', 'min:1'],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'verification_status' => ['nullable', 'in:0,1'],
            'category_ids' => ['nullable', 'array', 'max:100'],
            'category_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'seller_ids' => ['nullable', 'array', 'max:100'],
            'seller_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'user_ids' => ['nullable', 'array', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'verification_statuses' => ['nullable', 'array', 'max:2'],
            'verification_statuses.*' => ['required', 'in:0,1', 'distinct'],
            ...$this->dateRangeFilterRules(),
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'date_range' => ['bail', 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $bounds = explode(' / ', $value);
                if (count($bounds) !== 2 || Validator::make(['from' => $bounds[0], 'to' => $bounds[1]], [
                    'from' => ['required', 'date_format:Y-m-d'],
                    'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                ])->fails()) {
                    $fail(__('validation.date_format', ['attribute' => $attribute, 'format' => 'YYYY-MM-DD / YYYY-MM-DD']));
                }
            }],
        ];
    }

    /** @return array<int, string> */
    public function selected(string $key): array
    {
        return array_map(strval(...), $this->validated($key) ?? []);
    }
}
