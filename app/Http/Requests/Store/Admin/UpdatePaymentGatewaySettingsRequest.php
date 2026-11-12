<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Store\Admin\Concerns\ConfirmsCurrentPassword;
use App\Models\AdminAuditLog;
use App\Support\StorePaymentGateways;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Payment methods page (StorePaymentGateways): where the enabled list comes
 * from, and per gateway its switch, mode, currency and credentials, posted as
 * `gateways[<gateway>][<field>]`.
 *
 * A gateway's fields are one group. Every field is optional while the group is
 * empty (`.env` applies); once one is typed, each required field is required
 * (`required_with` the others) and each secret must be in effect: typed, or
 * saved, readable, not removed and still for the same account (the
 * identifying field and the `tied` ones unchanged). A saved secret is never
 * kept for another account: otherwise anyone allowed on this page could point
 * it at an account they control.
 *
 * The browser checks a copy of these rules (resources/js/lib/paymentGatewaySettingsRules.json,
 * kept equal by PaymentGatewaySettingsClientValidationParityTest); the
 * `after()` checks read the saved settings and stay on the server.
 *
 * Whoever controls these settings can redirect the shop's payments, so a save
 * asks for the signed-in staff member's own password, throttled with the other
 * sensitive pages (ConfirmsCurrentPassword). Neither it nor any secret is ever
 * logged, flashed or saved in plain text.
 */
class UpdatePaymentGatewaySettingsRequest extends FormRequest
{
    use ConfirmsCurrentPassword;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['enabled_source' => ['required', 'string', 'in:'.implode(',', StorePaymentGateways::SOURCES)]];

        foreach (StorePaymentGateways::gateways() as $gateway => $spec) {
            $prefix = "gateways.{$gateway}";
            $rules["{$prefix}.".StorePaymentGateways::ENABLED] = ['nullable', 'boolean'];

            if (isset($spec['mode']['setting'])) {
                $rules["{$prefix}.mode"] = ['required', 'string', 'in:'.implode(',', StorePaymentGateways::MODES)];
            }

            $group = array_keys($spec['fields']);

            foreach ($spec['fields'] as $field => $fieldSpec) {
                $fieldRules = [];

                if ($fieldSpec['required'] && ! $fieldSpec['secret']) {
                    $others = array_map(fn (string $other): string => "{$prefix}.{$other}", array_values(array_diff($group, [$field])));
                    $fieldRules[] = 'required_with:'.implode(',', $others);
                }

                $fieldRules[] = 'nullable';
                $fieldRules[] = 'string';

                if (isset($fieldSpec['modes'])) {
                    $fieldRules[] = 'in:'.implode(',', StorePaymentGateways::MODES);
                } elseif (isset($fieldSpec['in'])) {
                    $fieldRules[] = 'in:'.implode(',', $fieldSpec['in']);
                } else {
                    $fieldRules[] = 'max:'.$fieldSpec['max'];
                    $fieldRules[] = 'regex:'.$fieldSpec['pattern'];
                }

                $rules["{$prefix}.{$field}"] = $fieldRules;

                if ($fieldSpec['secret']) {
                    $rules["{$prefix}.{$field}_clear"] = ['nullable', 'boolean'];
                }
            }
        }

        $rules['current_password'] = ['required', 'string', 'current_password:store'];

        return $rules;
    }

    protected function passwordFailedAction(): string
    {
        return AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_PASSWORD_FAILED;
    }

    protected function passwordFailedMessage(): string
    {
        return 'A wrong current password was entered on the Payment methods page.';
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->refuseUnknownKeys($validator);

            $gateways = $this->input('gateways');
            if (! is_array($gateways)) {
                return;
            }

            foreach (StorePaymentGateways::gateways() as $gateway => $spec) {
                $input = is_array($gateways[$gateway] ?? null) ? $gateways[$gateway] : [];
                $prefix = "gateways.{$gateway}";

                if (! $spec['flow'] && filter_var($input[StorePaymentGateways::ENABLED] ?? false, FILTER_VALIDATE_BOOL)) {
                    $validator->errors()->add("{$prefix}.enabled", __('store_admin.payment_gateway_has_no_flow', ['gateway' => $spec['name']]));
                }

                $this->checkSecrets($validator, $gateway, $spec, $input);

                if ($gateway === 'stripe') {
                    $this->checkStripeKeysMatch($validator, $input);
                }
            }
        }];
    }

    /**
     * A secret field: refused with "remove" ticked, required once the group is
     * used unless the saved one is kept for the same account, and asked for
     * again when the account changed.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $input
     */
    private function checkSecrets(Validator $validator, string $gateway, array $spec, array $input): void
    {
        $identifying = StorePaymentGateways::clean($input[$spec['identifying']] ?? null, $spec['fields'][$spec['identifying']]);
        $stored = StorePaymentGateways::stored()[$gateway] ?? [];
        $sameAccount = $this->sameAccount($spec, $input, $stored);

        foreach ($spec['fields'] as $field => $fieldSpec) {
            if (! $fieldSpec['secret']) {
                continue;
            }

            $key = "gateways.{$gateway}.{$field}";
            $typed = StorePaymentGateways::clean($input[$field] ?? null, $fieldSpec) !== null;
            $clear = filter_var($input[$field.'_clear'] ?? false, FILTER_VALIDATE_BOOL);

            if ($clear && $typed) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $this->attributeName($key)]));

                continue;
            }

            if ($identifying === null || $typed || ! $fieldSpec['required']) {
                continue;
            }

            $saved = isset($stored[$field]) && ! StorePaymentGateways::secretUnreadable($gateway, $field);

            if ($saved && ! $clear && $sameAccount) {
                continue;
            }

            $validator->errors()->add($key, $saved && ! $clear
                ? __('store_admin.payment_gateway_secret_must_be_entered_again', ['field' => $this->attributeName($key)])
                : __('validation.required', ['attribute' => $this->attributeName($key)]));
        }
    }

    /**
     * Whether the identifying field and the `tied` fields are what the saved
     * secrets were saved with.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $input
     * @param  array<string, array{value: string, secret: bool}>  $stored
     */
    private function sameAccount(array $spec, array $input, array $stored): bool
    {
        foreach ([$spec['identifying'], ...$spec['tied']] as $field) {
            $typed = StorePaymentGateways::clean($input[$field] ?? null, $spec['fields'][$field]);

            if ($typed !== ($stored[$field]['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stripe's publishable and secret keys must both be test keys or both be
     * live keys. A kept secret is compared as saved.
     *
     * @param  array<string, mixed>  $input
     */
    private function checkStripeKeysMatch(Validator $validator, array $input): void
    {
        $publishable = StorePaymentGateways::clean($input['publishable_key'] ?? null);
        $secret = StorePaymentGateways::clean($input['secret_key'] ?? null);

        if ($publishable === null) {
            return;
        }

        if ($secret === null && ! filter_var($input['secret_key_clear'] ?? false, FILTER_VALIDATE_BOOL)
            && StorePaymentGateways::usesAdmin('stripe')) {
            $secret = (string) StorePaymentGateways::get('stripe', 'secret_key');
        }

        $publishableMode = StorePaymentGateways::modeOfStripeKey($publishable);
        $secretMode = $secret === null ? null : StorePaymentGateways::modeOfStripeKey($secret);

        if ($publishableMode !== null && $secretMode !== null && $publishableMode !== $secretMode) {
            $validator->errors()->add('gateways.stripe.secret_key', __('store_admin.payment_gateway_stripe_keys_mismatch'));
        }
    }

    /**
     * Anything the form does not post is refused: an unknown top-level key, an
     * unknown gateway or an unknown field of a gateway.
     */
    private function refuseUnknownKeys(Validator $validator): void
    {
        foreach (array_diff(array_keys($this->except(['_token'])), ['enabled_source', 'gateways', 'current_password']) as $key) {
            $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
        }

        $gateways = $this->input('gateways');

        if ($gateways === null) {
            return;
        }

        if (! is_array($gateways)) {
            $validator->errors()->add('gateways', __('validation.array', ['attribute' => 'gateways']));

            return;
        }

        foreach ($gateways as $gateway => $fields) {
            $spec = StorePaymentGateways::gateways()[$gateway] ?? null;

            if ($spec === null || ! is_array($fields)) {
                $validator->errors()->add("gateways.{$gateway}", __('validation.prohibited', ['attribute' => "gateways.{$gateway}"]));

                continue;
            }

            $allowed = [StorePaymentGateways::ENABLED, ...array_keys($spec['fields'])];
            if (isset($spec['mode']['setting'])) {
                $allowed[] = 'mode';
            }
            foreach ($spec['fields'] as $field => $fieldSpec) {
                if ($fieldSpec['secret']) {
                    $allowed[] = $field.'_clear';
                }
            }

            foreach (array_diff(array_keys($fields), $allowed) as $field) {
                $validator->errors()->add("gateways.{$gateway}.{$field}", __('validation.prohibited', ['attribute' => "gateways.{$gateway}.{$field}"]));
            }
        }
    }

    private function attributeName(string $key): string
    {
        $name = __("validation.attributes.{$key}");

        return is_string($name) && $name !== "validation.attributes.{$key}" ? $name : $key;
    }
}
