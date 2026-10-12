<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\SearchesProductsByName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchProductsRequest extends FormRequest
{
    use SearchesProductsByName;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productSearchRules(),
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }
}
