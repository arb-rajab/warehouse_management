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

/**
 * The setup checklist of the store back office dashboard: every setting the
 * shop cannot run properly without, each one done or still to do.
 *
 * A check is only listed for staff whose permission can fix it (the permission
 * that guards its settings page); a check the user cannot fix is neither
 * shown as to do nor counted. `key` is the stable identifier the frontend maps
 * to its settings link. The dashboard's SMTP and checkout banners read their
 * state from the same checks (see `pending()`), so each rule lives here only.
 *
 * Each check reads at most one cheap query (exists()/first()); the settings
 * every check shares come from one `business_settings` read per `items()` call.
 */
class SetupChecklist
{
    /**
     * Check key => the permission that can fix it, in display order.
     *
     * @var array<string, string>
     */
    public const array PERMISSIONS = [
        'smtp' => 'smtp_settings',
        'paymentMethod' => 'payment_methods_configurations',
        'cashOnDeliveryProducts' => 'show_all_products',
        'shipping' => 'shipping_configuration',
        'firebasePush' => 'google_firebase_setting',
        'googleMaps' => 'google_map_setting',
        'currency' => 'currency_setup',
        'vatTax' => 'vat_&_tax_setup',
        'catalog' => 'show_all_products',
        'siteBasics' => 'general_settings',
        'language' => 'language_setup',
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
     * The checks `$user` may fix, in display order.
     *
     * @return list<array{key: string, done: bool, permission: string}>
     */
    public function items(StoreUser $user): array
    {
        $this->settings = null;
        $items = [];

        foreach (self::PERMISSIONS as $key => $permission) {
            if ($user->can($permission)) {
                $items[] = ['key' => $key, 'done' => $this->isDone($key), 'permission' => $permission];
            }
        }

        return $items;
    }

    /**
     * How many of the listed checks are done, out of how many are listed.
     *
     * @param  list<array{key: string, done: bool, permission: string}>  $items
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
     * The keys of the listed checks that are still to do.
     *
     * @param  list<array{key: string, done: bool, permission: string}>  $items
     * @return list<string>
     */
    public function pending(array $items): array
    {
        return array_values(array_map(
            fn (array $item): string => $item['key'],
            array_filter($items, fn (array $item): bool => ! $item['done']),
        ));
    }

    /**
     * The Inertia prop: the key and state of each listed check, and the count.
     *
     * @param  list<array{key: string, done: bool, permission: string}>  $items
     * @return array{items: list<array{key: string, done: bool}>, done: int, total: int}
     */
    public function payload(array $items): array
    {
        return [
            'items' => array_map(fn (array $item): array => ['key' => $item['key'], 'done' => $item['done']], $items),
            ...$this->progress($items),
        ];
    }

    private function isDone(string $key): bool
    {
        return match ($key) {
            'smtp' => $this->smtp(),
            'paymentMethod' => $this->paymentMethod(),
            'cashOnDeliveryProducts' => $this->cashOnDeliveryProducts(),
            'shipping' => $this->shipping(),
            'firebasePush' => $this->firebasePush(),
            'googleMaps' => $this->googleMaps(),
            'currency' => $this->currency(),
            'vatTax' => $this->vatTax(),
            'catalog' => $this->catalog(),
            'siteBasics' => $this->siteBasics(),
            'language' => $this->language(),
        };
    }

    /**
     * Not done only while both the SMTP username and password are empty (what
     * the dashboard's SMTP warning has always tested): one of them set counts
     * as configured.
     */
    private function smtp(): bool
    {
        return ! (config('mail.mailers.smtp.username') == null && config('mail.mailers.smtp.password') == null);
    }

    /**
     * Whether GET /payment-types can list anything: the same settings it reads.
     */
    private function paymentMethod(): bool
    {
        foreach (['cash_payment', 'wallet_system', 'iyzico', 'bkash', 'nagad', 'sslcommerz_payment'] as $setting) {
            if ($this->setting($setting) == 1) {
                return true;
            }
        }

        return addon_is_activated('offline_payment') && ManualPaymentMethod::query()->exists();
    }

    /**
     * Done unless cash payment is on while no product allows cash on delivery
     * (the app lists cash on delivery only when both are set).
     */
    private function cashOnDeliveryProducts(): bool
    {
        return $this->setting('cash_payment') != 1 || Product::query()->where('cash_on_delivery', 1)->exists();
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
     *   means the cost was never set).
     * - seller_wise_shipping: the in-house cost `shipping_cost_admin` has been
     *   saved as a number (each seller's own cost lives on their shop).
     * - area_wise_shipping: at least one active city with a cost above zero
     *   (an area without a cost prices delivery at nothing).
     * - carrier_wise_shipping: at least one active carrier that either ships
     *   free or has a range with a price for a zone (the carriers checkout can
     *   list).
     *
     * An unset or unknown type is never done.
     */
    private function shipping(): bool
    {
        $type = $this->setting('shipping_type');

        return match (true) {
            ! in_array($type, self::SHIPPING_TYPES, true) => false,
            $type === 'flat_rate' => is_numeric($this->setting('flat_rate_shipping_cost')),
            $type === 'seller_wise_shipping' => is_numeric($this->setting('shipping_cost_admin')),
            $type === 'area_wise_shipping' => City::query()->where('status', 1)->where('cost', '>', 0)->exists(),
            $type === 'carrier_wise_shipping' => Carrier::query()->active()->where(
                fn ($query) => $query->where('free_shipping', 1)
                    ->orWhereHas('carrier_ranges', fn ($ranges) => $ranges->whereHas('carrier_range_prices')),
            )->exists(),
            default => true,
        };
    }

    private function firebasePush(): bool
    {
        return app(FcmPushService::class)->isConfigured();
    }

    /**
     * The key the storefront and seller pages load Google Maps with: the one
     * saved on the Google map settings page, else MAP_API_KEY
     * (see GoogleMapsKey).
     */
    private function googleMaps(): bool
    {
        return GoogleMapsKey::resolve($this->setting('google_map_api_key')) !== null;
    }

    /**
     * Prices are formatted through the default currency, which must be set and
     * must still exist.
     */
    private function currency(): bool
    {
        $id = $this->setting('system_default_currency');

        return is_numeric($id) && Currency::query()->whereKey((int) $id)->exists();
    }

    private function vatTax(): bool
    {
        return Tax::query()->where('tax_status', 1)->exists();
    }

    private function catalog(): bool
    {
        return Product::query()->where('published', 1)->exists() && Category::query()->exists();
    }

    private function siteBasics(): bool
    {
        foreach (['site_name', 'contact_email', 'contact_phone', 'header_logo'] as $setting) {
            if (blank($this->setting($setting))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The app's default language (`app.default_language`, a language code) has
     * to exist and be active.
     */
    private function language(): bool
    {
        $code = config('app.default_language');

        return filled($code) && Language::query()->where('code', $code)->where('status', 1)->exists();
    }

    /**
     * A `business_settings` value the way get_setting() reads it (the first
     * row of that type), from one query shared by every check.
     */
    private function setting(string $key): ?string
    {
        $this->settings ??= BusinessSetting::query()->whereIn('type', self::SETTINGS)->orderBy('id')
            ->get(['type', 'value'])->unique('type')->pluck('value', 'type')->all();

        return $this->settings[$key] ?? null;
    }
}
