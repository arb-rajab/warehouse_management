<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Store\Admin\Concerns\ValidatesCity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    use ValidatesCity;

    protected function prepareForValidation(): void
    {
        $this->merge(['cost' => $this->input('cost', 0)]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cityRules(editing: false);
    }
}
