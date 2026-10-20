<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Controllers\Store\Admin\TripOrderController;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The trip orders list's filters: a search term, one of the Blade list's
 * delivery statuses and an order date range.
 */
class FilterTripOrdersRequest extends FormRequest
{
    use FiltersPerPage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            'search' => ['nullable', 'string', 'max:100'],
            'delivery_status' => ['nullable', 'string', Rule::in(TripOrderController::FILTER_STATUSES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
