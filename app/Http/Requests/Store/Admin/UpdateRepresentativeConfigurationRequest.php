<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRepresentativeConfigurationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['default_representative_check' => $this->boolean('default_representative_check')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'default_representative_check' => ['required', 'boolean'],
            'default_representative_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('is_rep', 1),
            ],
        ];
    }
}
