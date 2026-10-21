<?php

namespace App\Http\Requests;

use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "Register your shop" (shop.frontend.seller_form), the storefront's seller
 * registration. Every field is required, as ShopController::store's inline
 * rules required them, and now limited to its column: users.name and
 * users.email are varchar(191), shops.name varchar(200) and shops.address
 * varchar(500), so a longer value was a MySQL error. The shop's slug is made
 * from its name, so it stays within the 255 characters of shops.slug.
 *
 * Only a guest registers: ShopController::create shows the form to nobody
 * else, and a signed-in account posting here by hand is turned away the same
 * way, before its input is read, instead of being converted into a seller.
 */
class StoreShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! Auth::check();
    }

    /**
     * Back, with the notice the registration page gives this account, or the
     * store's generic one for an account type the page shows nothing to.
     */
    protected function failedAuthorization(): void
    {
        /** @var StoreUser $user */
        $user = Auth::user();
        flash(self::refusalFor($user) ?? translate('Sorry! Something went wrong.'))->error();

        throw new HttpResponseException(back());
    }

    /**
     * Why the seller registration page turns `$user` away, or null for an
     * account type it simply shows nothing to.
     */
    public static function refusalFor(StoreUser $user): ?string
    {
        return match ($user->user_type) {
            'admin', 'customer' => translate('Admin or Customer can not be a seller'),
            'seller' => translate('This user already a seller'),
            default => null,
        };
    }

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
