<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencySettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['system_default_currency' => ['required_without:symbol_format', 'integer', Rule::exists('currencies', 'id')->where('status', 1)], 'symbol_format' => ['required_without:system_default_currency', 'integer', Rule::in([1, 2, 3, 4])], 'decimal_separator' => ['required_with:symbol_format', 'integer', Rule::in([1, 2])], 'no_of_decimals' => ['required_with:symbol_format', 'integer', Rule::in([0, 1, 2, 3])]];
    }
}
