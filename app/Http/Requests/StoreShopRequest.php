<?php

namespace App\Http\Requests;

use App\Models\StoreUser;
use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use LogicException;

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
 * With reCAPTCHA on, the widget's token is verified with Google (Recaptcha).
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
        flash(self::refusalFor($user) ?? __('seller.shop.sorry_something_went_wrong'))->error();

        throw new HttpResponseException(back());
    }

    /**
     * Why the seller registration page turns `$user` away, or null for an
     * account type it simply shows nothing to.
     */
    public static function refusalFor(StoreUser $user): ?string
    {
        $key = match ($user->user_type) {
            'admin', 'customer' => 'seller.shop.admin_or_customer_can_not_be_a_seller',
            'seller' => 'seller.shop.this_user_already_a_seller',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $message = __($key);
        if (! is_string($message)) {
            throw new LogicException('Seller registration refusal translations must be strings.');
        }

        return $message;
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
            'g-recaptcha-response' => Recaptcha::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return Recaptcha::messages();
    }
}
