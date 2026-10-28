<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAddress;
use App\Models\Address;
use App\Models\StoreUser;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The storefront's "New Address" form (shop.frontend.partials.address_modal).
 * The address, postal code and phone are required: the form has always
 * required them, and the order's shipping address, the invoice and the
 * delivery read them. Its errors go to their own bag, so the profile form on
 * the same page does not show them.
 */
class StoreAddressRequest extends FormRequest
{
    use ValidatesAddress;

    /** @var string */
    protected $errorBag = 'storeAddress';

    /**
     * A customer saves addresses for themselves, a representative for the
     * customer they are ordering for (AddressPolicy), checked before the input.
     */
    public function authorize(): bool
    {
        $address = new Address;
        $address->user_id = $this->ownerId();

        return Gate::allows('manage', $address);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->addressRules(),
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            'customer_id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * Whose address this is: the `customer_id` sent, else the representative's
     * current customer, else the signed-in account.
     */
    public function ownerId(): int
    {
        if ($this->has('customer_id')) {
            return is_numeric($this->input('customer_id')) ? (int) $this->input('customer_id') : 0;
        }

        /** @var StoreUser $user */
        $user = $this->user();

        return $user->is_rep ? ($user->current_customer->id ?? $user->id) : $user->id;
    }

    /**
     * The flash message the endpoint has always shown for a missing phone, and
     * the first error otherwise, beside the errors under each field.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        flash($errors->has('phone') && blank($this->input('phone')) ? __('storefront.auth.phone_number_is_required') : $errors->first())->error();

        parent::failedValidation($validator);
    }
}
