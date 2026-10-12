<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;

class FilterBrandsRequest extends FormRequest
{
    use FiltersPerPage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->perPageRules(), 'search' => ['nullable', 'string', 'max:255']];
    }
}
