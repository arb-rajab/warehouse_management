<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use App\Services\OrderService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterOrdersRequest extends FormRequest
{
    use FiltersPerPage;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [...$this->perPageRules(),
            'search' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'delivery_status' => ['nullable', Rule::in(OrderService::DELIVERY_STATUSES)],
            'date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2} to \d{4}-\d{2}-\d{2}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('date')) && preg_match('/^(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})$/', $this->input('date'), $matches)) {
            $this->merge(['from' => $matches[1], 'to' => $matches[2]]);
        }
    }
}
