<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePalletRequest extends FormRequest
{
    use ResolvesSlotFromCoordinates, ValidatesOptionalNote;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->coordinateRules(),
            ...$this->noteRules(),
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'expiration_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->resolveSlot($validator);
        });
    }
}
