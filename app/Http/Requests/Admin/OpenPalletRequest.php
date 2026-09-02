<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesBoxesCount;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenPalletRequest extends FormRequest
{
    use ValidatesBoxesCount, ValidatesOptionalNote;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->boxesCountRules(),
            ...$this->confirmEmptyRules(),
            ...$this->noteRules(),
        ];
    }
}
