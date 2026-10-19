<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreProductList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The for-sale, published, featured and today's-deal product-list switches: each posts the switch's new state as `status`.
 */
class UpdateProductListFlagRequest extends FormRequest
{
    use ValidatesStoreProductList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->productListRules(), 'status' => ['required', 'boolean']];
    }
}
