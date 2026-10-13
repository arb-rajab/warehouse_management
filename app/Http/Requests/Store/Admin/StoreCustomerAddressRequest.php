<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerAddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('status', 1)],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')->where('country_id', $this->input('country_id'))->where('status', 1)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('state_id', $this->input('state_id'))->where('status', 1)],
            'postal_code' => ['nullable', 'string', 'max:255'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
