<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCustomer;
use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    use ValidatesStoreCustomer;

    /** Get the validation rules that apply to the request.
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        abort_unless($customer instanceof StoreUser, 404);

        return [...$this->customerRules($customer->id, false), 'is_rep' => ['sometimes', 'boolean']];
    }
}
