<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesDiscountDateRange;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandDiscountRequest extends FormRequest
{
    use ValidatesDiscountDateRange;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'discount' => ['required', 'numeric', 'min:0'],
            'seller_product_discount' => ['sometimes', 'boolean'],
            'date_range' => $this->discountDateRangeRules(),
        ];
    }
}
