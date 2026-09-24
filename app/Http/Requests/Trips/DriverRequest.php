<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CheckpointType;

class DriverRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            // 'company_name' => 'required|string|max:255',
            'password' => 'sometimes|string|min:6|confirmed',
            'email' => 'required|email|unique:users,email',
            // 'tax_number' => 'required|max:100',
            // 'bank_account_number' => 'required|max:100',
            // 'trade_license' => 'required|file|mimes:doc,docx,pdf,png,jpeg,bmb|max:20000',
            'country' => 'required|string',
            'phone' => 'required|string',
            // 'company_address' => 'required|string',
            // 'shipping_address' => 'required|string'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => translate('The driver name is required.'),
            'name.string' => translate('The driver name must be a string.'),
            'name.max' => translate('The driver name may not be greater than 255 characters.'),

            'password.sometimes' => translate('Password is required'),
            'password.confirmed' => translate('Password confirmation does not match'),
            'password.min' => translate('Minimum 6 digits required for password'),

            'email.required' => translate('The email field is required.'),
            'email.email' => translate('The email must be a valid email.'),
            'email.unique' => translate('The email has already been taken.'),

            'phone.required' => translate('The phone field is required.'),
            'phone.string' => translate('The phone field must be a string.'),

            'country.required' => translate('The country field is required.'),
            'country.string' => translate('The country field must be a string.'),
        ];
    }
}
