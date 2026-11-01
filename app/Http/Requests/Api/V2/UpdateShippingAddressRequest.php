<?php

namespace App\Http\Requests\Api\V2;

use App\Models\Address;
use Illuminate\Support\Facades\Gate;

/**
 * POST /user/shipping/update: SaveShippingAddressRequest's rules for an address
 * the caller may change. The address is found and its ownership checked before
 * the input, so an address that does not exist is a 404 and someone else's a
 * 403 whatever the payload holds.
 */
class UpdateShippingAddressRequest extends SaveShippingAddressRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', Address::findOrFail($this->input('id')));
    }
}
