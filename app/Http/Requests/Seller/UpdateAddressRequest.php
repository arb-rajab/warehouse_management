<?php

namespace App\Http\Requests\Seller;

use App\Models\Address;
use Illuminate\Support\Facades\Gate;

/**
 * The seller panel's edit-address form (shop.seller.profile.address_edit_modal):
 * the same fields as a new address, for an address the seller owns
 * (AddressPolicy, checked before the input). `address_id` is the form's own
 * hidden copy of the route's id, sent back so a failed update can reopen
 * that address's form; only the route's id is ever written.
 */
class UpdateAddressRequest extends StoreAddressRequest
{
    /** @var string */
    protected $errorBag = 'updateAddress';

    private ?Address $targetAddress = null;

    public function authorize(): bool
    {
        return Gate::allows('manage', $this->targetAddress());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'address_id' => ['sometimes', 'integer']];
    }

    /**
     * The address the route names; a 404 when there is none.
     */
    public function targetAddress(): Address
    {
        return $this->targetAddress ??= Address::query()->whereKey($this->route('id'))->firstOrFail();
    }
}
