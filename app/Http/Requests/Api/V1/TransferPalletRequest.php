<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class TransferPalletRequest extends FormRequest
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
            ...$this->coordinateRules('to_'),
            ...$this->noteRules(),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->resolveTransferDestination($validator, 'to_');
        });
    }
}
