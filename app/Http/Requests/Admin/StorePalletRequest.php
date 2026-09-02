<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePalletRequest extends FormRequest
{
    use ValidatesOptionalNote;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->noteRules(),
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'expiration_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
