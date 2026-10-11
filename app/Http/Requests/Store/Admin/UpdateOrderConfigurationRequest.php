<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderConfigurationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['minimum_order_amount_check' => ['required', 'boolean'], 'minimum_order_amount' => ['required', 'numeric', 'min:0'], 'default_specific_product' => ['required', 'string', 'max:255', Rule::exists('products', 'mat_id')]];
    }
}
