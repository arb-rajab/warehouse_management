<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreProductList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The selection of one page of the in-house or all-products list. Whether
 * every member is a product that list shows is checked under lock by the
 * controller, before anything is deleted.
 */
class BulkDeleteProductsRequest extends FormRequest
{
    use ValidatesStoreProductList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->productListRules(),
            'id' => ['required', 'array', 'min:1', 'max:100'],
            'id.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
