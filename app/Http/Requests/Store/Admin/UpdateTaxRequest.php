<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Store\Admin\Concerns\ValidatesTax;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRequest extends FormRequest
{
    use ValidatesTax;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->taxRules();
    }
}
