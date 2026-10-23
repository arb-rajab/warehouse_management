<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\ValidatesAddress;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The seller panel's "New Address" form (shop.seller.profile.index), which
 * validated nothing. The address and phone are required, as the form has
 * always required them; the form has no postal code (it is commented out
 * there), so it stays optional. State and city, which the form required
 * although an empty `states` or `cities` table left nothing to pick, are
 * optional. The address is always the signed-in seller's.
 */
class StoreAddressRequest extends FormRequest
{
    use ValidatesAddress;

    /** @var string */
    protected $errorBag = 'storeAddress';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...$this->addressRules(), 'address' => ['required', 'string', 'max:255']];
    }
}
