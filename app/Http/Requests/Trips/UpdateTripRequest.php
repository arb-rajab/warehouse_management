<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TripStatus;
use Illuminate\Validation\Rules\Enum;

class UpdateTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status' => ['required', Rule::in(array_column(TripStatus::cases(), 'value'))],
            'notes' => 'nullable|string|max:65535', // text col
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $statusEnumValues = implode(', ', array_column(TripStatus::cases(), 'value'));
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'The status must be one of the following values: ' . $statusEnumValues . '.',
        ];
    }
}
