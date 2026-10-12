<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FilterAddressOptionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['country_id' => ['nullable', 'integer', 'exists:countries,id'], 'state_id' => ['nullable', 'integer', 'exists:states,id']];
    }
}
