<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreProductList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Duplicating or deleting one product from the in-house or all-products list.
 */
class ProductListActionRequest extends FormRequest
{
    use ValidatesStoreProductList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productListRules();
    }
}
