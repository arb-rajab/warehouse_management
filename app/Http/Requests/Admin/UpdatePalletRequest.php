<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesBoxesCount;
use App\Http\Requests\Concerns\ValidatesPalletContents;
use App\Http\Requests\Concerns\ValidatesReturnTo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePalletRequest extends FormRequest
{
    use ValidatesBoxesCount, ValidatesPalletContents, ValidatesReturnTo;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->returnToRules(),
            ...$this->palletContentsRules(),
            ...$this->confirmEmptyRules(),
            // Unlike creating a pallet, editing one must not require a
            // future-or-today date: a pallet can already be expired (that's
            // an expected, normal state) and an admin must still be able to
            // correct its product/remaining_boxes without also being forced
            // to change the expiration date.
            'expiration_date' => ['nullable', 'date'],
            // Sets remaining_boxes directly (an admin correction), unlike
            // open()/removeBoxes()'s boxes_count (an amount to subtract, via
            // ValidatesBoxesCount) — so 0 is a valid value here.
            'remaining_boxes' => ['required', 'integer', 'min:0'],
        ];
    }
}
