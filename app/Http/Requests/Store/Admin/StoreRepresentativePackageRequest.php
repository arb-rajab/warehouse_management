<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\RepresentativePackageRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepresentativePackageRequest extends FormRequest
{
    use RepresentativePackageRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->representativePackageRules();
    }
}
