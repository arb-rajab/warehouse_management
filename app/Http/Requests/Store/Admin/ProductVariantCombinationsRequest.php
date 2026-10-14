<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreProductVariants;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProductVariantCombinationsRequest extends FormRequest
{
    use ValidatesStoreProductVariants;

    /**
     * Get the validation rules that apply to the request.
     *
     * The unit price only fills each row's default price, as the Blade table
     * echoed whatever the price field held. The edit form's endpoint names a
     * product, which must be a physical one (404 otherwise, before any
     * validation).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        abort_if($product instanceof Product && (int) $product->getAttribute('digital') !== 0, 404);

        return [
            ...$this->productVariantRules(),
            'unit_price' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateVariantCombinations($validator)];
    }
}
