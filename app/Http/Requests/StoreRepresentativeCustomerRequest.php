<?php

namespace App\Http\Requests;

use App\Models\StoreUser;
use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A representative's "Add Customer" form (shop.frontend.user.create_user),
 * posting to CustomerController::store_customer. The twelve fields stay
 * required, as the inline rules required them, and are now limited to their
 * columns: users.name and users.email varchar(191), users.phone varchar(20),
 * users.country varchar(30), users.company_name and both addresses
 * varchar(255); the tax and bank numbers keep their 100. A longer value was a
 * MySQL error, and an array for most fields a 500. The trade license is one of
 * the kinds TradeLicenseStorage keeps (the old list's "bmb" matched nothing).
 * With reCAPTCHA on, the widget's token is verified with Google (Recaptcha).
 */
class StoreRepresentativeCustomerRequest extends FormRequest
{
    /**
     * Representatives only, checked before the input is read (a 403 as before).
     */
    public function authorize(): bool
    {
        return (bool) $this->user('store')?->is_rep;
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
            'company_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(StoreUser::class, 'email')],
            'tax_number' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:100'],
            'trade_license' => ['required', 'file', 'mimes:doc,docx,pdf,png,jpg,jpeg', 'max:20000'],
            'country' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:20'],
            'company_address' => ['required', 'string', 'max:255'],
            'shipping_address' => ['required', 'string', 'max:255'],
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
