<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMinimumOrderConfigurationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['minimum_order_amount_check' => $this->boolean('minimum_order_amount_check')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'minimum_order_amount_check' => ['required', 'boolean'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'default_specific_product' => ['required', 'string', Rule::exists('products', 'mat_id')],
        ];
    }
}
