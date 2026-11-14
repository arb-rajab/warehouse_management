<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\PaymentGatewaySetting;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The payment gateway settings staff save on the Payment methods page, over the
 * server's `.env` values (.ai/rules/admin-env-overrides.md).
 *
 * Precedence, per gateway and as one group: once a gateway's identifying field
 * (bKash's app key, SSLCommerz's store id, ...) is saved, every field of that
 * gateway (credentials, currency, and the mode where `.env` held it: Nagad,
 * N-Genius, Paytm, ToyyibPay) comes from the admin, an empty one included;
 * otherwise every field comes from the config (`.env`). A gateway never mixes
 * an admin value with a `.env` one: a `.env` secret would otherwise be sent to
 * an account typed in the admin. The six `<gateway>_sandbox` switches were
 * never `.env` and stay `business_settings` rows; this page edits them too.
 *
 * The enabled list (which gateways' routes answer and may be offered) follows
 * `STORE_PAYMENT_GATEWAYS` until staff choose it here; from then on every
 * gateway has an `enabled` row, so "chosen here, all off" means none enabled.
 *
 * Secrets are stored encrypted with the application key and decrypted only by
 * get(). Nothing is laid over config(): consumers read get(), so no console
 * boot, `config:cache` or job payload can hold a decrypted secret.
 *
 * @phpstan-type GatewaySpec array{name: string, identifying: string, flow: bool, offered: string|null, mode: array<string, string>, fixed_currency?: string, tied: list<string>, fields: array<string, array<string, mixed>>}
 */
final class StorePaymentGateways
{
    /** The cache entry holding the saved rows (secrets still encrypted). */
    public const string CACHE_KEY = 'payment_gateway_settings';

    /** The row that holds a gateway's switch while the admin chooses the list. */
    public const string ENABLED = 'enabled';

    /** The modes the page offers; each gateway maps them to its own config value. */
    public const array MODES = ['sandbox', 'live'];

    /** Where the enabled list comes from. */
    public const array SOURCES = ['env', 'admin'];

    /**
     * Values a gateway needs that only the server's `.env` can set (never typed on
     * the page, never shown): `<gateway> => [<field> => [<env name>, <config key>]]`.
     * The page shows each as set or not set and the setup checklist names it.
     */
    public const array ENV_ONLY = [
        'iyzico' => ['identity_number' => ['IYZICO_IDENTITY_NUMBER', 'services.iyzico.identity_number']],
    ];

    /** Gateways whose live endpoints or flow the provider has not confirmed (the page says so while one is on in live mode). */
    public const array LIVE_UNCONFIRMED = ['ngenius', 'aamarpay'];

    public const string CURRENCY = '/^[A-Z]{3}$/D';

    private const string ALNUM = '/^[A-Za-z0-9]+$/D';

    private const string ID = '/^[A-Za-z0-9_-]+$/D';

    private const string WORD = '/^[A-Za-z0-9_]+$/D';

    /** A credential that never holds whitespace (a secret, a token). */
    private const string TOKEN = '/^\S+$/D';

    /** A key body, with or without its PEM header and footer lines. */
    private const string KEY_BODY = '/^[A-Za-z0-9+\/=\s-]+$/D';

    private const string BASE64 = '/^[A-Za-z0-9+\/=]+$/D';

    private const string UUID = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/D';

    private const int CACHE_SECONDS = 600;

    /**
     * Every gateway the page edits.
     *
     * - `identifying`: the field whose saved value switches the whole group to the admin.
     * - `flow`: the store has a payment flow (controllers, routes) for it. Stripe,
     *   Paytm and ToyyibPay have none: their keys can be stored ready, but they
     *   cannot be enabled.
     * - `offered`: the Features activation switch that offers it at checkout.
     * - `mode`: `setting` (a `business_settings` switch, `default` when no row),
     *   `field` (the `mode` field of the group) or `derived` (from a key prefix).
     * - `fixed_currency`: a gateway that charges in one currency only.
     * - `tied`: fields besides the identifying one that a saved secret belongs to
     *   (a change asks for the secrets again).
     * - `fields`: `config` (the `.env`-fed config path), `env`, `secret`,
     *   `required` (once the group is used), `max`, `pattern`, `in`, `modes`
     *   (page mode => config value; `fallback` for any other config value),
     *   `textarea`, `key_body` (PEM armour and whitespace stripped on save).
     *
     * @var array<string, array<string, mixed>>
     */
    public const array GATEWAYS = [
        'bkash' => [
            'name' => 'bKash', 'identifying' => 'app_key', 'flow' => true, 'offered' => 'bkash',
            'mode' => ['setting' => 'bkash_sandbox', 'default' => 'sandbox'], 'tied' => ['username'],
            'fields' => [
                'app_key' => ['config' => 'services.bkash.app_key', 'env' => 'BKASH_CHECKOUT_APP_KEY', 'secret' => false, 'required' => true, 'max' => 100, 'pattern' => self::ALNUM],
                'app_secret' => ['config' => 'services.bkash.app_secret', 'env' => 'BKASH_CHECKOUT_APP_SECRET', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'username' => ['config' => 'services.bkash.username', 'env' => 'BKASH_CHECKOUT_USER_NAME', 'secret' => false, 'required' => true, 'max' => 100, 'pattern' => self::TOKEN],
                'password' => ['config' => 'services.bkash.password', 'env' => 'BKASH_CHECKOUT_PASSWORD', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'currency' => ['config' => 'services.bkash.currency', 'env' => 'BKASH_CURRENCY', 'secret' => false, 'required' => true, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'nagad' => [
            'name' => 'Nagad', 'identifying' => 'merchant_id', 'flow' => true, 'offered' => 'nagad',
            'mode' => ['field' => 'mode'], 'fixed_currency' => 'BDT', 'tied' => [],
            'fields' => [
                'merchant_id' => ['config' => 'nagad.merchant_id', 'env' => 'NAGAD_MERCHANT_ID', 'secret' => false, 'required' => true, 'max' => 20, 'pattern' => '/^[0-9]{6,20}$/D'],
                'merchant_number' => ['config' => 'nagad.merchant_number', 'env' => 'NAGAD_MERCHANT_NUMBER', 'secret' => false, 'required' => false, 'max' => 11, 'pattern' => '/^01[0-9]{9}$/D'],
                'pg_public_key' => ['config' => 'nagad.pg_public_key', 'env' => 'NAGAD_PG_PUBLIC_KEY', 'secret' => false, 'required' => true, 'max' => 4096, 'pattern' => self::KEY_BODY, 'textarea' => true, 'key_body' => true],
                'merchant_private_key' => ['config' => 'nagad.merchant_private_key', 'env' => 'NAGAD_MERCHANT_PRIVATE_KEY', 'secret' => true, 'required' => true, 'max' => 8192, 'pattern' => self::KEY_BODY, 'textarea' => true, 'key_body' => true],
                'mode' => ['config' => 'nagad.sandbox_mode', 'env' => 'NAGAD_MODE', 'secret' => false, 'required' => true, 'modes' => ['sandbox' => 'sandbox', 'live' => 'live'], 'fallback' => 'live'],
            ],
        ],
        'sslcommerz' => [
            'name' => 'SSLCommerz', 'identifying' => 'store_id', 'flow' => true, 'offered' => 'sslcommerz_payment',
            'mode' => ['setting' => 'sslcommerz_sandbox', 'default' => 'live'], 'tied' => [],
            'fields' => [
                'store_id' => ['config' => 'services.sslcommerz.store_id', 'env' => 'SSLCZ_STORE_ID', 'secret' => false, 'required' => true, 'max' => 100, 'pattern' => self::ID],
                'store_password' => ['config' => 'services.sslcommerz.store_password', 'env' => 'SSLCZ_STORE_PASSWD', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'currency' => ['config' => 'services.sslcommerz.currency', 'env' => 'SSLCZ_CURRENCY', 'secret' => false, 'required' => true, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'iyzico' => [
            'name' => 'iyzico', 'identifying' => 'api_key', 'flow' => true, 'offered' => 'iyzico',
            'mode' => ['setting' => 'iyzico_sandbox', 'default' => 'live'], 'tied' => [],
            'fields' => [
                'api_key' => ['config' => 'services.iyzico.api_key', 'env' => 'IYZICO_API_KEY', 'secret' => false, 'required' => true, 'max' => 255, 'pattern' => self::ID],
                'secret_key' => ['config' => 'services.iyzico.secret_key', 'env' => 'IYZICO_SECRET_KEY', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'currency' => ['config' => 'services.iyzico.currency', 'env' => 'IYZICO_CURRENCY_CODE', 'secret' => false, 'required' => true, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'payhere' => [
            'name' => 'PayHere', 'identifying' => 'merchant_id', 'flow' => true, 'offered' => 'payhere',
            'mode' => ['setting' => 'payhere_sandbox', 'default' => 'live'], 'tied' => [],
            'fields' => [
                'merchant_id' => ['config' => 'services.payhere.merchant_id', 'env' => 'PAYHERE_MERCHANT_ID', 'secret' => false, 'required' => true, 'max' => 20, 'pattern' => '/^[0-9]{4,20}$/D'],
                'secret' => ['config' => 'services.payhere.secret', 'env' => 'PAYHERE_SECRET', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'currency' => ['config' => 'services.payhere.currency', 'env' => 'PAYHERE_CURRENCY', 'secret' => false, 'required' => false, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'aamarpay' => [
            'name' => 'AamarPay', 'identifying' => 'store_id', 'flow' => true, 'offered' => 'aamarpay',
            'mode' => ['setting' => 'aamarpay_sandbox', 'default' => 'live'], 'tied' => [],
            'fields' => [
                'store_id' => ['config' => 'services.aamarpay.store_id', 'env' => 'AAMARPAY_STORE_ID', 'secret' => false, 'required' => true, 'max' => 100, 'pattern' => self::ID],
                'signature_key' => ['config' => 'services.aamarpay.signature_key', 'env' => 'AAMARPAY_SIGNATURE_KEY', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'currency' => ['config' => 'services.aamarpay.currency', 'env' => 'AAMARPAY_CURRENCY', 'secret' => false, 'required' => true, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'ngenius' => [
            'name' => 'N-Genius', 'identifying' => 'outlet_id', 'flow' => true, 'offered' => 'ngenius',
            'mode' => ['field' => 'mode'], 'tied' => [],
            'fields' => [
                'outlet_id' => ['config' => 'services.ngenius.outlet_id', 'env' => 'NGENIUS_OUTLET_ID', 'secret' => false, 'required' => true, 'max' => 36, 'pattern' => self::UUID],
                'api_key' => ['config' => 'services.ngenius.api_key', 'env' => 'NGENIUS_API_KEY', 'secret' => true, 'required' => true, 'max' => 512, 'pattern' => self::BASE64],
                'currency' => ['config' => 'services.ngenius.currency', 'env' => 'NGENIUS_CURRENCY', 'secret' => false, 'required' => true, 'max' => 3, 'pattern' => self::CURRENCY],
                'mode' => ['config' => 'services.ngenius.mode', 'env' => 'NGENIUS_MODE', 'secret' => false, 'required' => true, 'modes' => ['sandbox' => 'uat', 'live' => 'live'], 'fallback' => 'sandbox'],
            ],
        ],
        'voguepay' => [
            'name' => 'VoguePay', 'identifying' => 'merchant_id', 'flow' => true, 'offered' => 'voguepay',
            'mode' => ['setting' => 'voguepay_sandbox', 'default' => 'live'], 'tied' => [],
            'fields' => [
                'merchant_id' => ['config' => 'services.voguepay.merchant_id', 'env' => 'VOGUE_MERCHANT_ID', 'secret' => false, 'required' => true, 'max' => 100, 'pattern' => self::ID],
                'currency' => ['config' => 'services.voguepay.currency', 'env' => 'VOGUE_CURRENCY', 'secret' => false, 'required' => false, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'toyyibpay' => [
            'name' => 'ToyyibPay', 'identifying' => 'category_code', 'flow' => false, 'offered' => null,
            'mode' => ['field' => 'mode'], 'fixed_currency' => 'MYR', 'tied' => [],
            'fields' => [
                'category_code' => ['config' => 'toyyibpay.category', 'env' => 'TOYYIBPAY_CATEGORY', 'secret' => false, 'required' => true, 'max' => 50, 'pattern' => self::ALNUM],
                'user_secret_key' => ['config' => 'toyyibpay.key', 'env' => 'TOYYIBPAY_KEY', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::ID],
                'mode' => ['config' => 'toyyibpay.mode', 'env' => 'TOYYIBPAY_MODE', 'secret' => false, 'required' => true, 'modes' => ['sandbox' => 'sandbox', 'live' => 'live'], 'fallback' => 'sandbox'],
            ],
        ],
        'stripe' => [
            'name' => 'Stripe', 'identifying' => 'publishable_key', 'flow' => false, 'offered' => null,
            'mode' => ['derived' => 'publishable_key'], 'tied' => [],
            'fields' => [
                'publishable_key' => ['config' => 'services.stripe.key', 'env' => 'STRIPE_KEY', 'secret' => false, 'required' => true, 'max' => 255, 'pattern' => '/^pk_(test|live)_[A-Za-z0-9]+$/D'],
                'secret_key' => ['config' => 'services.stripe.secret', 'env' => 'STRIPE_SECRET', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => '/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/D'],
                'currency' => ['config' => 'services.stripe.currency', 'env' => 'STRIPE_CURRENCY', 'secret' => false, 'required' => false, 'max' => 3, 'pattern' => self::CURRENCY],
            ],
        ],
        'paytm' => [
            'name' => 'Paytm', 'identifying' => 'merchant_id', 'flow' => false, 'offered' => null,
            'mode' => ['field' => 'mode'], 'fixed_currency' => 'INR', 'tied' => ['merchant_website'],
            'fields' => [
                'merchant_id' => ['config' => 'services.paytm-wallet.merchant_id', 'env' => 'PAYTM_MERCHANT_ID', 'secret' => false, 'required' => true, 'max' => 50, 'pattern' => self::ALNUM],
                'merchant_key' => ['config' => 'services.paytm-wallet.merchant_key', 'env' => 'PAYTM_MERCHANT_KEY', 'secret' => true, 'required' => true, 'max' => 255, 'pattern' => self::TOKEN],
                'merchant_website' => ['config' => 'services.paytm-wallet.merchant_website', 'env' => 'PAYTM_MERCHANT_WEBSITE', 'secret' => false, 'required' => true, 'max' => 50, 'pattern' => self::WORD],
                'channel' => ['config' => 'services.paytm-wallet.channel', 'env' => 'PAYTM_CHANNEL', 'secret' => false, 'required' => false, 'in' => ['WEB', 'WAP']],
                'industry_type' => ['config' => 'services.paytm-wallet.industry_type', 'env' => 'PAYTM_INDUSTRY_TYPE', 'secret' => false, 'required' => false, 'max' => 50, 'pattern' => self::WORD],
                'mode' => ['config' => 'services.paytm-wallet.env', 'env' => 'PAYTM_ENVIRONMENT', 'secret' => false, 'required' => true, 'modes' => ['sandbox' => 'local', 'live' => 'production'], 'fallback' => 'sandbox'],
            ],
        ],
    ];

    /** @var array<string, true> secrets already reported as unreadable in this process */
    private static array $warned = [];

    /**
     * Every gateway the page edits (GATEWAYS), typed for static analysis: the
     * constant's own literal type differs from gateway to gateway.
     *
     * @return array<string, GatewaySpec>
     */
    public static function gateways(): array
    {
        return self::GATEWAYS;
    }

    /**
     * The gateways that have a payment flow in the store.
     *
     * @return list<string>
     */
    public static function withFlow(): array
    {
        return array_keys(array_filter(self::gateways(), fn (array $gateway): bool => $gateway['flow']));
    }

    /**
     * The saved rows by gateway and field, secrets still encrypted. A missing
     * table or cache table (migrations, a fresh install) reads as nothing saved
     * and is not cached.
     *
     * @return array<string, array<string, array{value: string, secret: bool}>>
     */
    public static function stored(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => self::read());
        } catch (Throwable) {
            return [];
        }
    }

    /** Whether `$gateway`'s fields come from the admin: its identifying field is saved. */
    public static function usesAdmin(string $gateway): bool
    {
        $spec = self::spec($gateway);

        return isset(self::stored()[$gateway][$spec['identifying']]);
    }

    /**
     * The value `$gateway` runs with for `$field`: from the admin when the
     * group is saved there (a secret decrypted; an unreadable one is null),
     * otherwise the config (`.env`). A mode field returns the gateway's own
     * config value (`uat`, `production`, ...).
     */
    public static function get(string $gateway, string $field): mixed
    {
        $spec = self::spec($gateway)['fields'][$field]
            ?? throw new InvalidArgumentException("Payment gateway [{$gateway}] has no field [{$field}].");

        if (! self::usesAdmin($gateway)) {
            return config($spec['config']);
        }

        $row = self::stored()[$gateway][$field] ?? null;

        if ($row === null) {
            return null;
        }

        if ($spec['secret']) {
            return self::decrypt($row['value'], $gateway, $field);
        }

        return isset($spec['modes']) ? ($spec['modes'][$row['value']] ?? null) : $row['value'];
    }

    /**
     * The currency `$gateway` charges in (GatewayCurrency): its effective
     * currency field, else the config (`services.<gateway>.currency`).
     */
    public static function currency(string $gateway): string
    {
        $value = isset(self::gateways()[$gateway]['fields']['currency'])
            ? self::get($gateway, 'currency')
            : config("services.$gateway.currency");

        return strtoupper(trim((string) $value));
    }

    /**
     * The effective mode on the page's scale (`sandbox`/`live`), or null when
     * it cannot be told (Stripe without a publishable key).
     */
    public static function mode(string $gateway): ?string
    {
        $mode = self::spec($gateway)['mode'];

        if (isset($mode['setting'])) {
            return self::settingMode($mode['setting'], $mode['default']);
        }

        if (isset($mode['derived'])) {
            return self::modeOfStripeKey((string) self::get($gateway, $mode['derived']));
        }

        return self::normalizeMode($gateway, self::get($gateway, $mode['field']));
    }

    /** `sandbox` for a `*_test_` Stripe key, `live` for a `*_live_` one, else null. */
    public static function modeOfStripeKey(string $key): ?string
    {
        return match (true) {
            (bool) preg_match('/^(pk|sk|rk)_test_/', $key) => 'sandbox',
            (bool) preg_match('/^(pk|sk|rk)_live_/', $key) => 'live',
            default => null,
        };
    }

    /**
     * What the page shows of `$gateway`'s effective settings, and where they
     * come from: public values in full, secrets only as set or not set.
     *
     * @return array<string, array{value?: string|null, set?: bool, source: 'admin'|'env'}>
     */
    public static function effective(string $gateway): array
    {
        $source = self::usesAdmin($gateway) ? 'admin' : 'env';
        $effective = [];

        foreach (self::spec($gateway)['fields'] as $field => $spec) {
            $value = self::get($gateway, $field);

            $effective[$field] = match (true) {
                $spec['secret'] => ['set' => filled($value), 'source' => $source],
                isset($spec['modes']) => ['value' => self::normalizeMode($gateway, $value), 'source' => $source],
                default => ['value' => filled($value) ? (string) $value : null, 'source' => $source],
            };
        }

        return $effective;
    }

    /**
     * The required fields of `$gateway` with no effective value (saved here or
     * in `.env`). The Payment methods page warns about them, naming each field,
     * and the setup checklist lists them for an enabled gateway.
     *
     * @return list<string>
     */
    public static function missing(string $gateway): array
    {
        $missing = [];

        foreach (self::spec($gateway)['fields'] as $field => $spec) {
            if ($spec['required'] && blank(self::get($gateway, $field))) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * The `.env`-only values of `$gateway` (ENV_ONLY) that are not set, as field names.
     *
     * @return list<string>
     */
    public static function envOnlyMissing(string $gateway): array
    {
        return array_keys(array_filter(self::ENV_ONLY[$gateway] ?? [], fn (array $spec): bool => ! self::envOnlySet($spec[1])));
    }

    /** Whether an ENV_ONLY config value is set (not empty, not blank). */
    public static function envOnlySet(string $configKey): bool
    {
        return trim((string) config($configKey)) !== '';
    }

    /** Whether `$gateway` is on in live mode while the provider has not confirmed its live setup (LIVE_UNCONFIRMED). */
    public static function liveUnconfirmed(string $gateway): bool
    {
        return in_array($gateway, self::LIVE_UNCONFIRMED, true) && self::isEnabled($gateway) && self::mode($gateway) === 'live';
    }

    /** Whether a saved secret exists but can no longer be decrypted (the application key changed). */
    public static function secretUnreadable(string $gateway, string $field): bool
    {
        $row = self::stored()[$gateway][$field] ?? null;

        return $row !== null && $row['secret'] && self::decrypt($row['value'], $gateway, $field) === null;
    }

    /** `admin` once the enabled list is chosen on the page, else `env` (STORE_PAYMENT_GATEWAYS). */
    public static function enabledSource(): string
    {
        foreach (self::stored() as $fields) {
            if (isset($fields[self::ENABLED])) {
                return 'admin';
            }
        }

        return 'env';
    }

    /**
     * The gateways whose routes answer and that the checkout may offer:
     * chosen on the page, else STORE_PAYMENT_GATEWAYS.
     *
     * @return list<string>
     */
    public static function enabled(): array
    {
        return self::enabledFrom(self::stored());
    }

    public static function isEnabled(string $gateway): bool
    {
        return in_array($gateway, self::enabled(), true);
    }

    /**
     * STORE_PAYMENT_GATEWAYS as the config holds it.
     *
     * @return list<string>
     */
    public static function environmentEnabled(): array
    {
        return array_values(array_filter((array) config('store.enabled_payment_gateways', []), 'is_string'));
    }

    /**
     * Save the Payment methods page. For each gateway: an empty identifying
     * field removes the whole group (secrets included), so `.env` applies
     * again; otherwise every field is saved as typed (empty deletes it), an
     * empty secret keeps the saved one and `<field>_clear` removes it. The
     * `<gateway>_sandbox` switches are written as `business_settings` rows.
     * With `enabled_source` = `admin` every gateway gets its `enabled` row
     * (a gateway without a flow is always off); with `env` they are removed.
     *
     * Returns what changed, `<gateway>.<field>` => `set`/`changed`/`removed`,
     * `<gateway>.enabled` => `enabled`/`disabled` and `enabled_source` =>
     * `set`/`removed`: names and kinds only, never a value. Secrets are
     * compared decrypted. `$whenChanged` runs inside the transaction when
     * something changed (the audit row).
     *
     * @param  array<string, mixed>  $data  validated UpdatePaymentGatewaySettingsRequest input
     * @param  (Closure(array<string, string>): mixed)|null  $whenChanged
     * @return array<string, string>
     */
    public static function save(array $data, ?int $userId, ?Closure $whenChanged = null): array
    {
        $changes = DB::transaction(function () use ($data, $userId, $whenChanged): array {
            $before = self::snapshot();
            $chosenHere = ($data['enabled_source'] ?? 'env') === 'admin';

            foreach (self::gateways() as $gateway => $spec) {
                $input = is_array($data['gateways'][$gateway] ?? null) ? $data['gateways'][$gateway] : [];

                self::saveGroup($gateway, $spec, $input, $userId);

                if (isset($spec['mode']['setting']) && in_array($input['mode'] ?? null, self::MODES, true)) {
                    self::writeSetting($spec['mode']['setting'], $input['mode'] === 'sandbox' ? '1' : '0');
                }

                $on = $spec['flow'] && filter_var($input[self::ENABLED] ?? false, FILTER_VALIDATE_BOOL);
                self::write($gateway, self::ENABLED, $chosenHere ? ($on ? '1' : '0') : null, false, $userId);
            }

            $changes = self::changes($before, self::snapshot());

            if ($changes !== [] && $whenChanged !== null) {
                $whenChanged($changes);
            }

            return $changes;
        });

        self::forget();

        return $changes;
    }

    /** Writers forget the rows and `business_settings` (the sandbox switches). */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('business_settings');
    }

    /**
     * The setting key of `$gateway`'s spec, or an exception for an unknown gateway.
     *
     * @return GatewaySpec
     */
    public static function spec(string $gateway): array
    {
        return self::gateways()[$gateway] ?? throw new InvalidArgumentException("Unknown payment gateway [{$gateway}].");
    }

    /** A value read from the config or saved, on the page's sandbox/live scale. */
    public static function normalizeMode(string $gateway, mixed $value): string
    {
        $spec = self::spec($gateway)['fields']['mode'];
        $value = strtolower(trim((string) $value));
        $mode = array_search($value, array_map('strtolower', $spec['modes']), true);

        return is_string($mode) ? $mode : $spec['fallback'];
    }

    /**
     * Normalises a typed value: trimmed, empty as null; a currency upper-cased;
     * a key body without its PEM header and footer and without whitespace.
     *
     * @param  array<string, mixed>  $spec
     */
    public static function clean(mixed $value, array $spec = []): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (! empty($spec['key_body'])) {
            $value = (string) preg_replace(['/-----[A-Z ]+-----/', '/\s+/'], '', $value);
        }

        if (($spec['pattern'] ?? null) === self::CURRENCY) {
            $value = strtoupper($value);
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  GatewaySpec  $spec
     * @param  array<string, mixed>  $input
     */
    private static function saveGroup(string $gateway, array $spec, array $input, ?int $userId): void
    {
        $identifying = self::clean($input[$spec['identifying']] ?? null, $spec['fields'][$spec['identifying']]);

        foreach ($spec['fields'] as $field => $fieldSpec) {
            if ($identifying === null) {
                self::write($gateway, $field, null, $fieldSpec['secret'], $userId);

                continue;
            }

            if ($fieldSpec['secret']) {
                if (filter_var($input[$field.'_clear'] ?? false, FILTER_VALIDATE_BOOL)) {
                    self::write($gateway, $field, null, true, $userId);
                } elseif (($secret = self::clean($input[$field] ?? null, $fieldSpec)) !== null) {
                    self::writeSecret($gateway, $field, $secret, $userId);
                }

                continue;
            }

            $value = self::clean($input[$field] ?? null, $fieldSpec);

            if (isset($fieldSpec['modes']) && ! in_array($value, self::MODES, true)) {
                $value = null;
            }

            self::write($gateway, $field, $value, false, $userId);
        }
    }

    /** A typed secret replaces the saved one only when it differs (decrypted). */
    private static function writeSecret(string $gateway, string $field, string $secret, ?int $userId): void
    {
        $existing = self::row($gateway, $field);

        if ($existing !== null && $existing->is_secret) {
            $saved = self::decrypt($existing->value, $gateway, $field);
            if ($saved !== null && hash_equals($saved, $secret)) {
                return;
            }
        }

        self::write($gateway, $field, Crypt::encryptString($secret), true, $userId);
    }

    private static function write(string $gateway, string $field, ?string $value, bool $secret, ?int $userId): void
    {
        $existing = self::row($gateway, $field);

        if ($value === null) {
            $existing?->delete();

            return;
        }

        if ($existing !== null && $existing->value === $value && $existing->is_secret === $secret) {
            return;
        }

        $row = $existing ?? new PaymentGatewaySetting(['gateway' => $gateway, 'field' => $field]);
        $row->fill(['value' => $value, 'is_secret' => $secret, 'updated_by' => $userId])->save();
    }

    private static function row(string $gateway, string $field): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::query()->where('gateway', $gateway)->where('field', $field)->first();
    }

    /** The `lang IS NULL` row the settings pages write, as SettingsController::saveToggles() does. */
    private static function writeSetting(string $type, string $value): void
    {
        $setting = BusinessSetting::query()->where('type', $type)->whereNull('lang')->orderBy('id')->first() ?? new BusinessSetting;
        if ($setting->exists && (string) $setting->value === $value) {
            return;
        }

        $setting->type = $type;
        $setting->value = $value;
        $setting->save();
    }

    private static function settingMode(string $type, string $default): string
    {
        $value = BusinessSetting::query()->where('type', $type)->orderByRaw('lang IS NOT NULL')->orderBy('id')->value('value');

        if ($value === null) {
            return $default;
        }

        return (string) $value === '1' ? 'sandbox' : 'live';
    }

    /** @return array<string, array<string, array{value: string, secret: bool}>> */
    private static function read(): array
    {
        $rows = [];

        foreach (PaymentGatewaySetting::query()->orderBy('id')->get(['gateway', 'field', 'value', 'is_secret']) as $row) {
            if (isset(self::gateways()[$row->gateway])) {
                $rows[$row->gateway][$row->field] = ['value' => (string) $row->value, 'secret' => $row->is_secret];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, array{value: string, secret: bool}>>  $rows
     * @return list<string>
     */
    private static function enabledFrom(array $rows): array
    {
        $chosenHere = false;
        $enabled = [];

        foreach (self::gateways() as $gateway => $spec) {
            if (isset($rows[$gateway][self::ENABLED])) {
                $chosenHere = true;
                if ($spec['flow'] && $rows[$gateway][self::ENABLED]['value'] === '1') {
                    $enabled[] = $gateway;
                }
            }
        }

        return $chosenHere ? $enabled : self::environmentEnabled();
    }

    /**
     * The saved state a save compares: the rows, the sandbox switches, the
     * effective enabled list and its source.
     *
     * @return array{rows: array<string, array<string, array{value: string, secret: bool}>>, modes: array<string, string>, enabled: list<string>, source: string}
     */
    private static function snapshot(): array
    {
        $rows = self::read();
        $modes = [];

        foreach (self::gateways() as $gateway => $spec) {
            if (isset($spec['mode']['setting'])) {
                $modes[$gateway] = self::settingMode($spec['mode']['setting'], $spec['mode']['default']);
            }
        }

        $source = 'env';
        foreach ($rows as $fields) {
            if (isset($fields[self::ENABLED])) {
                $source = 'admin';
            }
        }

        return ['rows' => $rows, 'modes' => $modes, 'enabled' => self::enabledFrom($rows), 'source' => $source];
    }

    /**
     * @param  array{rows: array<string, array<string, array{value: string, secret: bool}>>, modes: array<string, string>, enabled: list<string>, source: string}  $before
     * @param  array{rows: array<string, array<string, array{value: string, secret: bool}>>, modes: array<string, string>, enabled: list<string>, source: string}  $after
     * @return array<string, string>
     */
    private static function changes(array $before, array $after): array
    {
        $changes = [];

        if ($before['source'] !== $after['source']) {
            $changes['enabled_source'] = $after['source'] === 'admin' ? 'set' : 'removed';
        }

        foreach (self::gateways() as $gateway => $spec) {
            $wasOn = in_array($gateway, $before['enabled'], true);
            $isOn = in_array($gateway, $after['enabled'], true);
            if ($wasOn !== $isOn) {
                $changes["{$gateway}.enabled"] = $isOn ? 'enabled' : 'disabled';
            }

            if (($before['modes'][$gateway] ?? null) !== ($after['modes'][$gateway] ?? null)) {
                $changes["{$gateway}.mode"] = 'changed';
            }

            foreach ($spec['fields'] as $field => $fieldSpec) {
                $old = $before['rows'][$gateway][$field]['value'] ?? null;
                $new = $after['rows'][$gateway][$field]['value'] ?? null;

                if ($old === $new) {
                    continue;
                }

                if ($old === null || $new === null) {
                    $changes["{$gateway}.{$field}"] = $old === null ? 'set' : 'removed';

                    continue;
                }

                if ($fieldSpec['secret']) {
                    $oldSecret = self::decrypt($old, $gateway, $field);
                    $newSecret = self::decrypt($new, $gateway, $field);
                    if ($oldSecret !== null && $newSecret !== null && hash_equals($oldSecret, $newSecret)) {
                        continue;
                    }
                }

                $changes["{$gateway}.{$field}"] = 'changed';
            }
        }

        return $changes;
    }

    private static function decrypt(string $encrypted, string $gateway, string $field): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            if (! isset(self::$warned["{$gateway}.{$field}"])) {
                self::$warned["{$gateway}.{$field}"] = true;
                Log::warning('A saved payment gateway secret cannot be decrypted with the application key; enter it again on the Payment methods page.', ['gateway' => $gateway, 'field' => $field]);
            }

            return null;
        }
    }
}
