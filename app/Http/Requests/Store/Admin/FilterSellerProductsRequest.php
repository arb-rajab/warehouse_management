<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersStoreProductList;
use Illuminate\Foundation\Http\FormRequest;

class FilterSellerProductsRequest extends FormRequest
{
    use FiltersStoreProductList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->productListFilterRules(),
            'user_id' => ['nullable', 'integer', 'min:1'],
            'approved' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeProductListFlags(['approved']);
    }
}
