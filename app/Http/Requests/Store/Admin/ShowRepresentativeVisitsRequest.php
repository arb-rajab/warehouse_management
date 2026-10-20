<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\StoreUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowRepresentativeVisitsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['user' => $this->route('user') instanceof StoreUser ? $this->route('user')->id : $this->route('user')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['user' => ['required', 'integer', 'min:1'], 'date' => ['nullable', 'date_format:Y-m-d']];
    }
}
