<?php

namespace App\Http\Requests;

use App\Models\Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * The storefront's edit-address form (shop.frontend.partials.address_edit_modal):
 * the same fields as a new address. `address_id` is the form's own hidden
 * copy of the route's id, sent back with the input so a failed update can
 * reopen that address's form; only the route's id is ever written.
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
        return [...Arr::except(parent::rules(), 'customer_id'), 'address_id' => ['sometimes', 'integer']];
    }

    /**
     * The address the route names; a 404 when there is none.
     */
    public function targetAddress(): Address
    {
        return $this->targetAddress ??= Address::query()->whereKey($this->route('id'))->firstOrFail();
    }
}
