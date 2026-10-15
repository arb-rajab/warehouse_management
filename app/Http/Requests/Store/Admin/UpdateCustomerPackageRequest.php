<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCustomerPackage;
use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPackageRequest extends FormRequest
{
    use ValidatesStoreCustomerPackage;
    use ValidatesStoreLanguage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->customerPackageRules($this->route('customerPackage')), ...$this->languageRules('required')];
    }
}
