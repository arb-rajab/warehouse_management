<?php

namespace App\Http\Requests\Concerns;

use App\Models\CustomerPackage;

trait ValidatesStoreCustomerPackage
{
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function customerPackageRules(?CustomerPackage $package = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'],
            'product_upload' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'logo' => ['nullable', 'integer', $this->retainableImageUploadRule('logo', $package?->logo)],
        ];
    }
}
