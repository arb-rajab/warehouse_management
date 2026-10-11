<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyFormatsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol_format' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'decimal_separator' => ['required', 'integer', Rule::in([1, 2])],
            'no_of_decimals' => ['required', 'integer', 'between:0,3'],
        ];
    }
}
