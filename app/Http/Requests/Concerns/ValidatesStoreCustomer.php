<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesStoreCustomer
{
    /** @return array<string, array<mixed>> */
    protected function customerRules(?int $customerId, bool $creating): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customerId)],
            'phone' => ['required', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($customerId)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', Rule::exists('countries', 'name')->where('status', 1)],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'member_serial' => ['nullable', 'string', 'max:255'],
            'AccSysID' => ['nullable', 'integer', 'min:0'],
            'trade_license' => ['nullable', 'file', 'mimes:doc,docx,pdf,png,jpg,jpeg', 'max:20000'],
        ];
    }
}
