<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesPalletSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/v1/pallets/change-product`: move the selected pallets (one or
 * many, any products, any warehouses, placed or not) to another, active
 * product, with the reason kept in the cell log.
 */
class ChangePalletProductRequest extends FormRequest
{
    use ValidatesPalletSelection;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->palletSelectionRules(),
            /** The product the pallets become (ids from `GET /api/v1/products`); it must exist and be active. */
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('published', 1)],
            /** Why the product is changed, 3 to 255 characters; kept in the history. */
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            /** `true` keeps the pallets' notes; by default they are cleared. */
            'keep_note' => ['sometimes', 'boolean'],
        ];
    }
}
