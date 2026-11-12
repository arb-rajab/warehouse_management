<?php

namespace App\Services\Store;

use App\Models\BusinessSetting;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Models\Language;
use App\Models\ManualPaymentMethod;
use App\Models\Product;
use App\Models\StoreUser;
use App\Models\Tax;
use App\Services\FcmPushService;
use App\Support\GoogleMapsKey;
use App\Support\StoreDefaultLanguage;
use App\Support\StoreMailSettings;
use App\Support\StorePaymentGateways;

/**
 * The setup checklist of the store back office dashboard: every setting the
 * shop cannot run properly without, each one done or still to do.
 *
 * A check is only listed for staff who hold every permission that guards the
 * pages fixing it; a check the user cannot fix is neither shown as to do nor
 * counted. `key` is the stable identifier of the check.
 *
 * A check that is not done always says why (.ai/rules/setup-checklist-reasons.md):
 * each `reasons` entry names the exact missing setting (`shipping.flat_rate_cost`),
 * the route of the page that sets it and the `highlight` key that page marks
 * and scrolls to (.ai/rules/deep-link-highlight.md), or null on a list page
 * where no single option exists. A check is done exactly when it has no reason.
 *
 * Each check reads at most one cheap query (exists()/first()); the settings
 * every check shares come from one `business_settings` read per `items()` call.
 */
class SetupChecklist
{
    /**
     * Check key => every permission needed to fix it, in display order.
     *
     * Site basics span three pages: the site name is on General settings, the
     * header logo on Website header and the contact details on Website footer.
     *
     * @var array<string, list<string>>
     */
    public const array PERMISSIONS = [
        'smtp' => ['smtp_settings'],
        'paymentMethod' => ['features_activation'],
        'paymentGateways' => ['payment_methods_configurations'],
        'cashOnDeliveryProducts' => ['show_all_products'],
        'shipping' => ['shipping_configuration'],
        'firebasePush' => ['google_firebase_setting'],
        'googleMaps' => ['google_map_setting'],
        'currency' => ['currency_setup'],
        'vatTax' => ['vat_&_tax_setup'],
        'catalog' => ['show_all_products'],
        'siteBasics' => ['general_settings', 'header_setup', 'footer_setup'],
        'language' => ['language_setup'],
    ];

    /**
     * Reason key => [route of the page that sets it, the key that page highlights].
     * A null highlight is a list page with no single option to mark.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    public const array TARGETS = [
        'smtp.mailer_log' => ['smtp_settings.index', 'mail_mailer'],
        'smtp.host' => ['smtp_settings.index', 'mail_host'],
        'smtp.username_and_password' => ['smtp_settings.index', 'mail_credentials'],
        'paymentMethod.none_enabled' => ['activation.index', 'cash_payment'],
        // An enabled gateway without a required field: one reason per field
        // (StorePaymentGateways::missing()), highlighting it on the Payment methods page.
        'paymentGateways.bkash_app_key' => ['payment_method.index', 'bkash.app_key'],
        'paymentGateways.bkash_app_secret' => ['payment_method.index', 'bkash.app_secret'],
        'paymentGateways.bkash_username' => ['payment_method.index', 'bkash.username'],
        'paymentGateways.bkash_password' => ['payment_method.index', 'bkash.password'],
        'paymentGateways.bkash_currency' => ['payment_method.index', 'bkash.currency'],
        'paymentGateways.nagad_merchant_id' => ['payment_method.index', 'nagad.merchant_id'],
        'paymentGateways.nagad_pg_public_key' => ['payment_method.index', 'nagad.pg_public_key'],
        'paymentGateways.nagad_merchant_private_key' => ['payment_method.index', 'nagad.merchant_private_key'],
        'paymentGateways.nagad_mode' => ['payment_method.index', 'nagad.mode'],
        'paymentGateways.sslcommerz_store_id' => ['payment_method.index', 'sslcommerz.store_id'],
        'paymentGateways.sslcommerz_store_password' => ['payment_method.index', 'sslcommerz.store_password'],
        'paymentGateways.sslcommerz_currency' => ['payment_method.index', 'sslcommerz.currency'],
        'paymentGateways.iyzico_api_key' => ['payment_method.index', 'iyzico.api_key'],
        'paymentGateways.iyzico_secret_key' => ['payment_method.index', 'iyzico.secret_key'],
        'paymentGateways.iyzico_currency' => ['payment_method.index', 'iyzico.currency'],
        'paymentGateways.payhere_merchant_id' => ['payment_method.index', 'payhere.merchant_id'],
        'paymentGateways.payhere_secret' => ['payment_method.index', 'payhere.secret'],
        'paymentGateways.aamarpay_store_id' => ['payment_method.index', 'aamarpay.store_id'],
        'paymentGateways.aamarpay_signature_key' => ['payment_method.index', 'aamarpay.signature_key'],
        'paymentGateways.aamarpay_currency' => ['payment_method.index', 'aamarpay.currency'],
        'paymentGateways.ngenius_outlet_id' => ['payment_method.index', 'ngenius.outlet_id'],
        'paymentGateways.ngenius_api_key' => ['payment_method.index', 'ngenius.api_key'],
        'paymentGateways.ngenius_currency' => ['payment_method.index', 'ngenius.currency'],
        'paymentGateways.ngenius_mode' => ['payment_method.index', 'ngenius.mode'],
        'paymentGateways.voguepay_merchant_id' => ['payment_method.index', 'voguepay.merchant_id'],
        'cashOnDeliveryProducts.no_product_allows_cod' => ['products.all', null],
        'shipping.type_unset' => ['shipping_configuration.index', 'shipping_type'],
        'shipping.flat_rate_cost' => ['shipping_configuration.index', 'flat_rate_shipping_cost'],
        'shipping.seller_admin_cost' => ['shipping_configuration.index', 'shipping_cost_admin'],
        'shipping.area_no_active_city_with_cost' => ['shipping_configuration.index', 'cities'],
        'shipping.carrier_none_usable' => ['shipping_configuration.index', 'carriers'],
        'firebasePush.project_unset' => ['google-firebase.index', 'firebase_server'],
        'firebasePush.credentials_missing' => ['google-firebase.index', 'firebase_server'],
        'googleMaps.api_key_unset' => ['google-map.index', 'google_map_api_key'],
        'currency.default_unset' => ['currency.index', 'system_default_currency'],
        'currency.default_missing' => ['currency.index', 'system_default_currency'],
        'vatTax.no_active_tax' => ['tax.index', null],
        'catalog.no_published_product' => ['products.all', null],
        'catalog.no_category' => ['categories.index', null],
        'siteBasics.site_name' => ['general_setting.index', 'site_name'],
        'siteBasics.contact_email' => ['website.footer', 'contact_email'],
        'siteBasics.contact_phone' => ['website.footer', 'contact_phone'],
        'siteBasics.header_logo' => ['website.header', 'header_logo'],
        'language.default_missing' => ['languages.index', 'default_language'],
        'language.default_inactive' => ['languages.index', 'default_language'],
    ];

    /** The shipping types the Shipping configuration page can save. */
    private const array SHIPPING_TYPES = [
        'product_wise_shipping', 'flat_rate', 'seller_wise_shipping', 'area_wise_shipping', 'carrier_wise_shipping',
    ];

    /** The settings the checks read; one query loads them all. */
    private const array SETTINGS = [
        'cash_payment', 'wallet_system', 'iyzico', 'bkash', 'nagad', 'sslcommerz_payment',
        'shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin',
        'system_default_currency', 'site_name', 'contact_email', 'contact_phone', 'header_logo',
        'google_map_api_key',
    ];

    /** @var array<string, string|null>|null */
    private ?array $settings = null;

    /**
     * Words a reason's text fills in, by reason key (the language reasons name
     * the language code); only a reason that has some carries `params`.
     *
     * @var array<string, array<string, string>>
     */
    private array $params = [];

    /**
     * The checks `$user` may fix, in display order, each with why it is not
     * done yet (empty when done).
     *
     * @return list<array{key: string, done: bool, permissions: list<string>, reasons: list<array{key: string, route: string, highlight: string|null, params?: array<string, string>}>}>
     */
    public function items(StoreUser $user): array
    {
        $this->settings = null;
        $this->params = [];
        $items = [];

        foreach (self::PERMISSIONS as $key => $permissions) {
            if (! $this->canAll($user, $permissions)) {
                continue;
            }

            $reasons = array_map(
                fn (string $reason): array => $this->target("{$key}.{$reason}", $user),
                $this->reasons($key),
            );
            $items[] = ['key' => $key, 'done' => $reasons === [], 'permissions' => $permissions, 'reasons' => $reasons];
        }

        return $items;
    }

    /**
     * How many of the listed checks are done, out of how many are listed.
     *
     * @param  list<array{key: string, done: bool}>  $items
     * @return array{done: int, total: int}
     */
    public function progress(array $items): array
    {
        return [
            'done' => count(array_filter($items, fn (array $item): bool => $item['done'])),
            'total' => count($items),
        ];
    }

    /**
     * The Inertia prop: the key, state and reasons of each listed check, and the count.
     *
     * @param  list<array{key: string, done: bool, permissions: list<string>, reasons: list<array{key: string, route: string, highlight: string|null, params?: array<string, string>}>}>  $items
     * @return array{items: list<array{key: string, done: bool, reasons: list<array{key: string, route: string, highlight: string|null, params?: array<string, string>}>}>, done: int, total: int}
     */
    public function payload(array $items): array
    {
        return [
            'items' => array_map(
                fn (array $item): array => ['key' => $item['key'], 'done' => $item['done'], 'reasons' => $item['reasons']],
                $items,
            ),
            ...$this->progress($items),
        ];
    }

    /** @param  list<string>  $permissions */
    private function canAll(StoreUser $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Where a reason is fixed. Categories are managed on their own page; staff
     * who may not open it are sent to the product list, where the category of
     * a product is chosen.
     *
     * @return array{key: string, route: string, highlight: string|null, params?: array<string, string>}
     */
    private function target(string $reason, StoreUser $user): array
    {
        [$route, $highlight] = self::TARGETS[$reason]
            ?? throw new \LogicException("Setup checklist reason [{$reason}] has no target.");

        if ($reason === 'catalog.no_category' && ! $user->can('view_product_categories')) {
            $route = 'products.all';
        }

        return ['key' => $reason, 'route' => $route, 'highlight' => $highlight]
            + (isset($this->params[$reason]) ? ['params' => $this->params[$reason]] : []);
    }

    /**
     * Why a check is not done: the short reason keys (prefixed by the check
     * key into TARGETS), empty when it is done.
     *
     * @return list<string>
     */
    private function reasons(string $key): array
    {
        return match ($key) {
            'smtp' => $this->smtp(),
            'paymentMethod' => $this->paymentMethod(),
            'paymentGateways' => $this->paymentGateways(),
            'cashOnDeliveryProducts' => $this->cashOnDeliveryProducts(),
            'shipping' => $this->shipping(),
            'firebasePush' => $this->firebasePush(),
            'googleMaps' => $this->googleMaps(),
            'currency' => $this->currency(),
            'vatTax' => $this->vatTax(),
            'catalog' => $this->catalog(),
            'siteBasics' => $this->siteBasics(),
            'language' => $this->language(),
            default => throw new \LogicException("Unknown setup checklist key [{$key}]."),
        };
    }

    /**
     * The effective mail settings (StoreMailSettings: saved on the SMTP page,
     * else the server's MAIL_* values), each reason highlighting its field:
     *
     * - mailer_log: the mailer is `log`, so emails are only written to the log;
     * - host: the SMTP host is empty;
     * - username_and_password: both the SMTP username and password are empty
     *   (what the dashboard's SMTP warning has always tested): one of them set
     *   counts as configured.
     *
     * Known limitation, kept on purpose: a mailer that needs no SMTP
     * credentials (ses, postmark, set only by MAIL_MAILER) still needs a
     * username or password to count as done.
     *
     * @return list<string>
     */
    private function smtp(): array
    {
        $mail = app(StoreMailSettings::class)->effective();

        return array_values(array_filter([
            $mail['mailer']['value'] === 'log' ? 'mailer_log' : null,
            blank($mail['host']['value']) ? 'host' : null,
            blank($mail['username']['value']) && blank($mail['password']['value']) ? 'username_and_password' : null,
        ]));
    }

    /**
     * Whether GET /payment-types can list anything: the same settings it reads.
     * Every one of them is a switch on Features activation; a gateway's switch
     * counts only while the gateway is also enabled (StorePaymentGateways:
     * chosen on the Payment methods page, else STORE_PAYMENT_GATEWAYS), as the
     * app lists it only then.
     *
     * @return list<string>
     */
    private function paymentMethod(): array
    {
        foreach (['cash_payment', 'wallet_system'] as $setting) {
            if ($this->setting($setting) == 1) {
                return [];
            }
        }

        foreach (['iyzico' => 'iyzico', 'bkash' => 'bkash', 'nagad' => 'nagad', 'sslcommerz_payment' => 'sslcommerz'] as $setting => $gateway) {
            if ($this->setting($setting) == 1 && StorePaymentGateways::isEnabled($gateway)) {
                return [];
            }
        }

        return addon_is_activated('offline_payment') && ManualPaymentMethod::query()->exists() ? [] : ['none_enabled'];
    }

    /**
     * Every enabled gateway has each required field in effect (saved on the
     * Payment methods page, else `.env`): one reason per missing field,
     * `<gateway>_<field>`, which the page highlights as `<gateway>.<field>`.
     *
     * @return list<string>
     */
    private function paymentGateways(): array
    {
        $reasons = [];

        foreach (StorePaymentGateways::enabled() as $gateway) {
            if (! isset(StorePaymentGateways::GATEWAYS[$gateway]) || ! StorePaymentGateways::GATEWAYS[$gateway]['flow']) {
                continue;
            }

            foreach (StorePaymentGateways::missing($gateway) as $field) {
                $reasons[] = "{$gateway}_{$field}";
            }
        }

        return $reasons;
    }

    /**
     * Done unless cash payment is on while no product allows cash on delivery
     * (the app lists cash on delivery only when both are set).
     *
     * @return list<string>
     */
    private function cashOnDeliveryProducts(): array
    {
        return $this->setting('cash_payment') != 1 || Product::query()->where('cash_on_delivery', 1)->exists()
            ? [] : ['no_product_allows_cod'];
    }

    /**
     * Done when `shipping_type` is one the Shipping configuration page can
     * save and the data checkout prices that type from exists
     * (getShippingCost() in app/Http/Helpers.php):
     *
     * - product_wise_shipping: done once the type is set; each product carries
     *   its own shipping cost, so there is no shop-wide data to require.
     * - flat_rate: `flat_rate_shipping_cost` has been saved as a number (zero
     *   counts: it is a deliberate free-shipping flat rate; a missing row
     *   means the cost was never set, and the page then shows the field empty).
     * - seller_wise_shipping: the in-house cost `shipping_cost_admin` has been
     *   saved as a number (each seller's own cost lives on their shop).
     * - area_wise_shipping: at least one active city with a cost above zero
     *   (an area without a cost prices delivery at nothing).
     * - carrier_wise_shipping: at least one active carrier that either ships
     *   free or has a range with a price for a zone (the carriers checkout can
     *   list).
     *
     * An unset or unknown type is never done (the page then shows no type chosen).
     *
     * @return list<string>
     */
    private function shipping(): array
    {
        $type = $this->setting('shipping_type');

        $done = match (true) {
            ! in_array($type, self::SHIPPING_TYPES, true) => null,
            $type === 'flat_rate' => is_numeric($this->setting('flat_rate_shipping_cost')),
            $type === 'seller_wise_shipping' => is_numeric($this->setting('shipping_cost_admin')),
            $type === 'area_wise_shipping' => City::query()->where('status', 1)->where('cost', '>', 0)->exists(),
            $type === 'carrier_wise_shipping' => Carrier::query()->active()->where(
                fn ($query) => $query->where('free_shipping', 1)
                    ->orWhereHas('carrier_ranges', fn ($ranges) => $ranges->whereHas('carrier_range_prices')),
            )->exists(),
            default => true,
        };

        return match (true) {
            $done === null => ['type_unset'],
            $done => [],
            $type === 'flat_rate' => ['flat_rate_cost'],
            $type === 'seller_wise_shipping' => ['seller_admin_cost'],
            $type === 'area_wise_shipping' => ['area_no_active_city_with_cost'],
            default => ['carrier_none_usable'],
        };
    }

    /**
     * Push needs FIREBASE_PROJECT and the FIREBASE_CREDENTIALS file on the
     * server (FcmPushService::isConfigured()); the Firebase settings page
     * shows both as read-only status, since only the server sets them.
     *
     * @return list<string>
     */
    private function firebasePush(): array
    {
        $push = app(FcmPushService::class);
        $path = $push->credentialsPath();

        return array_values(array_filter([
            $push->project() === null ? 'project_unset' : null,
            $path === null || ! is_file($path) ? 'credentials_missing' : null,
        ]));
    }

    /**
     * The key the storefront and seller pages load Google Maps with: the one
     * saved on the Google map settings page, else MAP_API_KEY
     * (see GoogleMapsKey).
     *
     * @return list<string>
     */
    private function googleMaps(): array
    {
        return GoogleMapsKey::resolve($this->setting('google_map_api_key')) === null ? ['api_key_unset'] : [];
    }

    /**
     * Prices are formatted through the default currency, which must be set and
     * must still exist.
     *
     * @return list<string>
     */
    private function currency(): array
    {
        $id = $this->setting('system_default_currency');

        return match (true) {
            ! is_numeric($id) => ['default_unset'],
            ! Currency::query()->whereKey((int) $id)->exists() => ['default_missing'],
            default => [],
        };
    }

    /** @return list<string> */
    private function vatTax(): array
    {
        return Tax::query()->where('tax_status', 1)->exists() ? [] : ['no_active_tax'];
    }

    /** @return list<string> */
    private function catalog(): array
    {
        return array_values(array_filter([
            Product::query()->where('published', 1)->exists() ? null : 'no_published_product',
            Category::query()->exists() ? null : 'no_category',
        ]));
    }

    /**
     * The site name (General settings), the header logo (Website header) and
     * the contact email and phone (Website footer); each one blank is a reason.
     *
     * @return list<string>
     */
    private function siteBasics(): array
    {
        return array_values(array_filter(
            ['site_name', 'contact_email', 'contact_phone', 'header_logo'],
            fn (string $setting): bool => blank($this->setting($setting)),
        ));
    }

    /**
     * The effective default language (StoreDefaultLanguage: chosen on the
     * Languages page, else DEFAULT_LANGUAGE) has to exist and be active. Each
     * reason carries `params.code`, the language it is about ("-" when none is
     * configured at all), so its text names it.
     *
     * @return list<string>
     */
    private function language(): array
    {
        $code = app(StoreDefaultLanguage::class)->get();
        $language = filled($code) ? Language::query()->where('code', $code)->first(['id', 'status']) : null;
        $reason = match (true) {
            $language === null => 'default_missing',
            (int) $language->status !== 1 => 'default_inactive',
            default => null,
        };

        if ($reason === null) {
            return [];
        }

        $this->params["language.{$reason}"] = ['code' => filled($code) ? (string) $code : '-'];

        return [$reason];
    }

    /**
     * A `business_settings` value, from one query shared by every check. The
     * row without a language wins, as the settings pages write and show that
     * one (SettingsController, WebsiteSettings); otherwise the first row of
     * that type, the way get_setting() reads it.
     */
    private function setting(string $key): ?string
    {
        $this->settings ??= BusinessSetting::query()->whereIn('type', self::SETTINGS)->orderBy('id')
            ->get(['type', 'value', 'lang'])->groupBy('type')
            ->map(fn ($rows): ?string => ($rows->firstWhere('lang', null) ?? $rows->first())?->value)->all();

        return $this->settings[$key] ?? null;
    }
}
