<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesReturnTo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmptyPalletRequest extends FormRequest
{
    use ValidatesOptionalNote, ValidatesReturnTo;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->noteRules(),
            ...$this->returnToRules(),
        ];
    }
}
