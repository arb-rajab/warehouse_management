<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The checkout's address pick (shop.frontend.shipping_info): one of the
 * customer's addresses is required. Whose address it is stays the
 * controller's check (AddressPolicy), after the cart is known not to be empty.
 */
class StoreShippingInfoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['address_id' => ['required', 'integer']];
    }

    /**
     * Keeps the message the step has always shown when no address was chosen.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (blank($this->input('address_id'))) {
            flash(__('storefront.checkout.please_add_shipping_address'))->warning();
        }

        parent::failedValidation($validator);
    }
}
