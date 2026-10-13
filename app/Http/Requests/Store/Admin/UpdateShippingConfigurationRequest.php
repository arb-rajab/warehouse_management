<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingConfigurationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin'])], 'shipping_type' => ['required_if:type,shipping_type', Rule::in(['product_wise_shipping', 'flat_rate', 'seller_wise_shipping', 'area_wise_shipping', 'carrier_wise_shipping'])], 'flat_rate_shipping_cost' => ['required_if:type,flat_rate_shipping_cost', 'numeric', 'min:0'], 'shipping_cost_admin' => ['required_if:type,shipping_cost_admin', 'numeric', 'min:0']];
    }
}
