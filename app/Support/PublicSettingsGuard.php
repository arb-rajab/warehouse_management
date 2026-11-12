<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The one place that decides which `business_settings` rows the public,
 * unauthenticated store API may hand out (`POST /api/v1/store/business-settings`
 * names the `type` values it wants; the GET list has its own allow-list on top
 * of this guard).
 *
 * It is a BLOCK-list: a requested key comes back unless it is sensitive. The
 * owner chose that over an allow-list or a sign-in (2026-11-12); the old mobile
 * apps can be forced to update through the minimum app version, so a blocked key
 * needs no compatibility. A key is blocked when
 *
 *  1. it is in `BLOCKED` or owned by a support class (`named()`: named
 *     credentials and internal state), or
 *  2. its name starts with one of `BLOCKED_PREFIXES` (the `mail_` and
 *     `payment_gateway_` families and the credential families of the usual
 *     payment and SMS providers), or
 *  3. its name ends in a secret-like word (`_secret`, `_key`, `_password`,
 *     `_token`, `_credentials`, `_encrypted`, ...) or carries one as a word
 *     (`stripe_secret_live`), or
 *  4. it is empty.
 *
 * `PUBLIC_EXCEPTIONS` are the keys that match rule 2 or 3 and are public by
 * design; an exception never overrides a named key.
 *
 * EVERY `business_settings` key the application writes or reads must be
 * classified here: blocked by one of the rules, or listed in `PUBLIC` below.
 * `tests/Feature/PublicSettingsGuardTest.php` fails for a key that is neither
 * (the admin pages' keys, the support classes' keys and every
 * `get_setting()` and `BusinessSetting::where('type', ...)` call with a literal
 * name in `app/` and `resources/views/`). The payment gateway settings add their keys
 * under `payment_gateway_`, which is blocked as a family: nothing to add here
 * unless one of them must be public (then list it in `PUBLIC_EXCEPTIONS` with
 * the reason, and in `PUBLIC`).
 *
 * See .ai/rules/admin-env-overrides.md ("Keep the rows private").
 */
final class PublicSettingsGuard
{
    /** The most keys one request may name; the rest are ignored. */
    public const int MAX_KEYS = 200;

    /**
     * Sensitive or internal keys, named. Lower case: names are compared
     * lower-cased. `named()` adds the keys of the support classes.
     *
     * @var list<string>
     */
    public const array BLOCKED = [
        // The reCAPTCHA secret's form field name (the stored row is the support class's).
        'recaptcha_secret_key',
        // The Google Maps key (GoogleMapsKey::SETTING, added by named()): only
        // signed-in customers' and sellers' pages print it (the address map) and
        // the admin page shows a mask, so it is not public.
        // The Firebase push credentials live in `.env` (FIREBASE_PROJECT, FIREBASE_CREDENTIALS);
        // the page's section name stays out as well.
        'firebase_server',
        // Code staff paste into every page. Printed there, never needed by the apps,
        // and free text that may carry a token.
        'header_script',
        'footer_script',
        // The representative account orders default to: a staff account reference.
        'default_representative_id',
        'default_representative_check',
    ];

    /**
     * Families blocked by name. `payment_gateway_` is the payment gateway
     * settings' prefix; the provider prefixes are for credentials a legacy
     * import or a future gateway could save.
     *
     * @var list<string>
     */
    public const array BLOCKED_PREFIXES = [
        'payment_gateway_',
        'mail_',
        'smtp_',
        'firebase_',
        'fcm_',
        'sms_',
        'twilio_',
        'nexmo_',
        'vonage_',
        'msg91_',
        'stripe_',
        'paypal_',
        'razorpay_',
        'paystack_',
        'flutterwave_',
        // Internal state, not for the apps: the catalogue field sources
        // (ProductFieldSourceService::KEY_PREFIX) and the seeders' tracking rows.
        'product_source_',
        'store_filter_seed_',
    ];

    /**
     * A name that ends in one of these words after a separator (`_`, `-`, `.`
     * or a space), or is the word itself: `stripe_secret`, `api_key`, `token`.
     *
     * @var list<string>
     */
    public const array BLOCKED_SUFFIX_WORDS = [
        'secret', 'secrets', 'key', 'keys', 'password', 'passwd', 'token', 'tokens',
        'credential', 'credentials', 'encrypted', 'salt', 'signature', 'passphrase',
    ];

    /**
     * A name that ends in one of these even without a separator (`apikey`,
     * `accesstoken`, `dbpassword`).
     *
     * @var list<string>
     */
    public const array BLOCKED_SUFFIXES = [
        'secret', 'password', 'passwd', 'token', 'credentials', 'credential', 'encrypted', 'passphrase', 'apikey',
    ];

    /**
     * A name that carries one of these as a whole word anywhere
     * (`stripe_secret_live`, `password_hash`).
     *
     * @var list<string>
     */
    public const array BLOCKED_WORDS = [
        'secret', 'secrets', 'password', 'passwd', 'token', 'tokens', 'credential', 'credentials', 'encrypted', 'passphrase',
    ];

    /**
     * Keys that match a rule above and stay public on purpose.
     *
     * - `recaptcha_site_key`: the reCAPTCHA SITE key, printed into every
     *   storefront form (`data-sitekey`) and meant for the browser; only the
     *   secret key is a credential.
     *
     * @var list<string>
     */
    public const array PUBLIC_EXCEPTIONS = [
        'recaptcha_site_key',
    ];

    /**
     * Every other key the application writes or reads, classified public: not
     * a credential, and either printed into the public pages, needed by the
     * apps, or a plain switch or number nothing can be abused with. Matched
     * case-insensitively. A key not listed here and not blocked above fails
     * the enforcement test: classify it when you add it.
     *
     * @var list<string>
     */
    public const array PUBLIC = [
        // Storefront identity, branding and SEO (printed into the pages)
        'site_name', 'website_name', 'site_motto', 'site_icon', 'header_logo', 'footer_logo', 'system_logo_black',
        'system_logo_white', 'admin_login_background', 'login_page_image', 'register_page_image', 'seller_login_page_bg',
        'delivery_boy_login_page_bg', 'base_color', 'base_hov_color', 'meta_title', 'meta_description', 'meta_keywords',
        'meta_image', 'current_version', 'timezone', 'default_language',
        // Header, home page, footer and banners
        'header_menu_labels', 'header_menu_links', 'header_stikcy', 'show_currency_switcher', 'helpline_number',
        'topbar_banner', 'topbar_banner_small', 'topbar_banner_medium', 'topbar_banner_link',
        'home_slider_images', 'home_slider_links', 'home_banner1_images', 'home_banner1_links', 'home_banner2_images',
        'home_banner3_images', 'home_categories', 'cupon_title', 'cupon_subtitle', 'cupon_background_color',
        'todays_deal_banner', 'todays_deal_banner_small', 'todays_deal_bg_color', 'flash_deal_banner',
        'flash_deal_banner_small', 'classified_banner_image', 'classified_banner_image_small', 'top_brands',
        'best_selling', 'about_us_description', 'contact_address', 'contact_phone', 'contact_email', 'widget_one',
        'widget_one_labels', 'widget_one_links', 'frontend_copyright_text', 'show_social_links', 'facebook_link',
        'twitter_link', 'instagram_link', 'youtube_link', 'linkedin_link', 'play_store_link', 'app_store_link',
        'seller_app_link', 'delivery_boy_app_link', 'payment_method_images', 'registration_tutorial_button',
        'registration_tutorial_text', 'registration_tutorial_link', 'cookies_agreement_text', 'show_cookies_agreement',
        'website_popup_content', 'show_website_popup', 'show_subscribe_form', 'terms', 'total_visits',
        // Public IDs and the reCAPTCHA site key (StoreIntegrationIds, StoreRecaptchaSettings; printed into the pages)
        'google_analytics_tracking_id', 'facebook_pixel_id', 'facebook_page_id', 'facebook_app_id', 'recaptcha_site_key',
        // Switches for third-party widgets and logins (on or off, no credential)
        'google_analytics', 'facebook_pixel', 'facebook_chat', 'facebook_comment', 'google_recaptcha', 'google_firebase',
        'facebook_login', 'google_login', 'twitter_login', 'apple_login',
        // Maps: the on switch and the shop's own pickup location
        'google_map', 'google_map_latitude', 'google_map_longtitude', 'google_maps_link', 'delivery_pickup_latitude',
        'delivery_pickup_longitude',
        // Money, shipping and ordering rules the apps and checkout read
        'system_default_currency', 'symbol_format', 'decimal_separator', 'no_of_decimals', 'shipping_type',
        'flat_rate_shipping_cost', 'shipping_cost_admin', 'minimum_order_amount', 'minimum_order_amount_check',
        'default_specific_product', 'delivery_lead_days', 'delivery_timezone', 'guest_checkout_active', 'refund_request_time',
        'refund_sticker', 'club_point_convert_rate', 'cmr_counter', 'verification_form',
        // Sales channels, payments and add-on switches (on or off) and sandbox flags
        'cash_payment', 'sslcommerz_payment', 'sslcommerz_sandbox', 'voguepay', 'voguepay_sandbox', 'payhere',
        'payhere_sandbox', 'ngenius', 'iyzico', 'iyzico_sandbox', 'bkash', 'bkash_sandbox', 'nagad', 'aamarpay',
        'aamarpay_sandbox', 'wallet_system', 'pickup_point', 'coupon_system', 'classified_product', 'vendor_system_activation',
        'conversation_system', 'email_verification', 'product_query_activation', 'product_manage_by_admin',
        'product_approve_by_admin', 'disable_image_optimization', 'color_filter_activation', 'pos_activation_for_seller',
        'seller_auction_product', 'seller_wholesale_product', 'qr_connection_system', 'delivery_boy_payment_type',
        // Seller commission settings
        'vendor_commission', 'vendor_commission_activation', 'category_wise_commission', 'minimum_seller_amount_withdraw',
        'delivery_boy_commission',
        // The back office's section switches (which menu parts are on; no value to abuse).
        // `SMTP_Section` is not listed: the `smtp_` prefix blocks it, and no app reads it.
        'In_House_Products_Section', 'Digital_Products_Section', 'Product_Reviews_Section', 'In_House_Orders_Section',
        'Pickup_Orders_Section', 'Payouts_Section', 'Payout_request_Section', 'Seller_Commission_Section',
        'Seller_Verify_Form_Section', 'Shop_Ratings', 'Reports_Section', 'In_House_Sales_Report', 'Products_Stock_Report',
        'Products_Wishlist_Report', 'User_Searches_Report', 'Commission_History_Report', 'Wallet_Recharge_History',
        'Blog_Section', 'Marketing_Section', 'Flash_deals', 'Newsletters', 'Support_Section', 'website_setup',
        'website_setup_header', 'website_setup_footer', 'website_setup_pages', 'website_setup_appearance',
        'Setup_configuration_section', 'Languages_section', 'currency_section', 'VAT_TAX_section',
        'Setup_pickup_point_section', 'Order_configuration_section', 'Socail_Media_logins_section',
        'Facebook_section', 'Setup_Shipping_section', 'Staffs_section',
    ];

    /**
     * `BLOCKED` plus the keys the support classes own: the SMTP server, login,
     * encrypted password and from address (StoreMailSettings), the encrypted
     * reCAPTCHA secret (StoreRecaptchaSettings) and the Google Maps key
     * (GoogleMapsKey). A field added to those classes is blocked without an
     * edit here.
     *
     * @return list<string>
     */
    public static function named(): array
    {
        return [
            ...self::BLOCKED,
            ...array_values(StoreMailSettings::KEYS),
            StoreRecaptchaSettings::KEYS['secret'],
            GoogleMapsKey::SETTING,
        ];
    }

    /**
     * Whether a `business_settings` type must not be handed out. Compared
     * trimmed and lower-cased, so spacing and letter case cannot get around
     * it; an empty name is blocked (it names nothing).
     */
    public static function isBlocked(string $type): bool
    {
        $name = self::normalize($type);

        if ($name === '') {
            return true;
        }

        if (in_array($name, self::named(), true)) {
            return true;
        }

        if (in_array($name, self::PUBLIC_EXCEPTIONS, true)) {
            return false;
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array(end($words), self::BLOCKED_SUFFIX_WORDS, true)
            || array_intersect($words, self::BLOCKED_WORDS) !== [];
    }

    /**
     * The names to query for a request's `keys` value: a comma-separated string,
     * or an array of such strings. Anything else (a number, a nested array,
     * nothing) names nothing. Trimmed, de-duplicated, blocked and empty names
     * left out, at most `MAX_KEYS`. The names keep their spelling, so a
     * case-sensitive database still matches the stored `type`.
     *
     * @return list<string>
     */
    public static function requested(mixed $keys): array
    {
        $names = [];

        foreach (is_array($keys) ? $keys : [$keys] as $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $name) {
                $name = trim($name);

                if ($name !== '' && ! self::isBlocked($name)) {
                    $names[$name] = $name;
                }
            }
        }

        return array_slice(array_values($names), 0, self::MAX_KEYS);
    }

    /**
     * The rows a response may carry: those whose own `type` is not blocked.
     * Apply it to the rows that were read, not only to the request, because
     * the database may match a `type` the request spelled differently (case,
     * trailing spaces).
     *
     * @param  iterable<array<string, mixed>|object>  $rows  arrays or models with a `type`
     * @return Collection<int, array<string, mixed>|object>
     */
    public static function filter(iterable $rows): Collection
    {
        return Collection::make($rows)
            ->reject(fn (array|object $row): bool => self::isBlocked((string) (is_array($row) ? ($row['type'] ?? '') : ($row->type ?? ''))))
            ->values();
    }

    private static function normalize(string $type): string
    {
        $trimmed = preg_replace('/^[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+|[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+$/u', '', $type);

        return $trimmed === null ? '' : mb_strtolower($trimmed);
    }
}
