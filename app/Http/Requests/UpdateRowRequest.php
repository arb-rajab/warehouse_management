<?php

namespace App\Http\Requests;

use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRowRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Row $row */
        $row = $this->route('row');

        return [
            'letter' => ['required', 'string', 'max:2', 'unique:rows,letter,'.$row->id],
            'cells_count' => ['required', 'integer', 'min:1'],
            'flats_count' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('cells_count') || $validator->errors()->has('flats_count')) {
                return;
            }

            /** @var Row $row */
            $row = $this->route('row');

            $dimensionsChanged = $this->integer('cells_count') !== $row->cells_count
                || $this->integer('flats_count') !== $row->flats_count;

            if (! $dimensionsChanged) {
                return;
            }

            if ($row->hasPallets()) {
                $validator->errors()->add('cells_count', __('messages.row_cannot_resize_has_pallets'));
            }
        });
    }
}
