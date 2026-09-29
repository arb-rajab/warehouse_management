<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CheckpointType;

class TripRequest extends FormRequest
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
            'driver_id' => 'nullable|integer|exists:users,id',
            'truck_id' => 'required|integer|exists:trucks,id',
            'due_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
            'checkpoint_type.*' => ['required', 'string', Rule::in(array_column(CheckpointType::cases(), 'value'))],
            'checkpoint_order.*' => 'required_without:checkpoint_customer.*|exists:orders,id',
            'checkpoint_customer.*' => 'required_without:checkpoint_order.*|exists:users,id',
            'checkpoint_notes.*' => 'nullable|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $statusEnumValues = implode(', ', array_column(CheckpointType::cases(), 'value'));
        return [
            'driver_id.required' => translate('The driver field is required.'),
            'driver_id.integer' => translate('The driver ID must be a valid integer.'),
            'driver_id.exists' => translate('The selected driver ID does not exist in our records.'),

            'truck_id.required' => translate('The truck field is required.'),
            'truck_id.integer' => translate('The truck ID must be a valid integer.'),
            'truck_id.exists' => translate('The selected truck ID does not exist in our records.'),

            'due_date.required' => translate('The due date field is required.'),
            'due_date.date' => translate('The due date must be a valid date.'),

            'notes.string' => translate('The notes must be a string.'),
            'notes.max' => translate('The notes may not be greater than 255 characters.'),

            'checkpoint_type.*.required' => translate('The checkpoint type field is required.'),
            'checkpoint_type.*.string' => translate('The checkpoint type must be a string.'),
            'checkpoint_type.*.in' => translate('The checkpoint type must be one of the following values: ' . $statusEnumValues . '.'),

            'checkpoint_order.*.required_without' => translate('The checkpoint order field is required when the checkpoint customer field is not provided.'),
            'checkpoint_order.*.exists' => translate('The selected checkpoint order does not exist in our records.'),

            'checkpoint_customer.*.required_without' => translate('The checkpoint customer field is required when the checkpoint order field is not provided.'),
            'checkpoint_customer.*.exists' => translate('The selected checkpoint customer does not exist in our records.'),

            'checkpoint_notes.*.string' => translate('The checkpoint notes must be a string.'),
        ];
    }
}
