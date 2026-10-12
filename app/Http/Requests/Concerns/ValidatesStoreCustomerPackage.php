<?php

namespace App\Http\Requests\Concerns;

use App\Models\CustomerPackage;
use Illuminate\Validation\Rule;

trait ValidatesStoreCustomerPackage
{
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function customerPackageRules(?CustomerPackage $package = null): array
    {
        $logo = $this->input('logo');
        $retainedLogo = (is_int($logo) || is_string($logo)) && $package?->logo !== null && (string) $logo === (string) $package->logo;

        return [
            'name' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/D'],
            'product_upload' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'logo' => ['nullable', 'integer', $retainedLogo ? Rule::exists('uploads', 'id')->where('type', 'image') : $this->imageUploadRule()],
        ];
    }
}
