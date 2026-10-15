<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterReviewsRequest extends FormRequest
{
    use FiltersPerPage;

    /** @return array<string, mixed> Get the validation rules that apply to the request. */
    public function rules(): array
    {
        return [...$this->perPageRules(), 'rating' => ['nullable', Rule::in(['rating,asc', 'rating,desc'])]];
    }
}
