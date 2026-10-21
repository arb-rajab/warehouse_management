<?php

namespace App\Http\Requests;

use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Register your shop" (shop.frontend.seller_form), the storefront's seller
 * registration. Every field is required, as ShopController::store's inline
 * rules required them, and now limited to its column: users.name and
 * users.email are varchar(191), shops.name varchar(200) and shops.address
 * varchar(500), so a longer value was a MySQL error. The shop's slug is made
 * from its name, so it stays within the 255 characters of shops.slug.
 */
class StoreShopRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(StoreUser::class, 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'shop_name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}
