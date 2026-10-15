<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCustomerPackage;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerPackageRequest extends FormRequest
{
    use ValidatesStoreCustomerPackage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->customerPackageRules();
    }
}
