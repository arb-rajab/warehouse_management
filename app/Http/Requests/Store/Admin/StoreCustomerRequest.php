<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCustomer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    use ValidatesStoreCustomer;

    /** Get the validation rules that apply to the request.
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->customerRules(null, true);
        if ($this->routeIs('customers.store')) {
            foreach (['company_name', 'country', 'tax_number', 'bank_account_number', 'company_address', 'shipping_address', 'trade_license'] as $field) {
                $rules[$field][0] = 'required';
            }
        }

        return $rules;
    }
}
