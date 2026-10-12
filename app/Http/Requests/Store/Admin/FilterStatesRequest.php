<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterStatesRequest extends FormRequest
{
    use FiltersPerPage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->perPageRules(), 'sort_state' => ['nullable', 'string', 'max:255'], 'sort_country' => ['nullable', 'integer', Rule::exists('countries', 'id')]];
    }
}
