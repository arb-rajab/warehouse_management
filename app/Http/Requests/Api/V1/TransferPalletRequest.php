<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Models\Pallet;
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
            $destination = $this->resolveSlot($validator, 'to_');

            if ($destination === null) {
                return;
            }

            /** @var Pallet $pallet */
            $pallet = $this->route('pallet');

            if ($destination->id === $pallet->cell_id) {
                $validator->errors()->add('to_cell_number', __('messages.pallet_already_at_location'));
            }
        });
    }
}
