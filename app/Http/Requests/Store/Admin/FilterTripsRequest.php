<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\TripStatus;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTripsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'array'],
            'status.*' => ['required', 'string', 'distinct', Rule::enum(TripStatus::class)],
        ];
    }
}
