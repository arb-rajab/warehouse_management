<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission the store back office enforces, with the section the role
 * form groups it under (.ai/archive/store-backoffice/changes-20261020.md §40). The one versioned list behind `store:sync-permissions`,
 * the migration that runs it on deploy and the seeder that gives fresh local
 * and e2e databases the rows. A new gate in routes/shop-admin.php, a Store
 * controller or storeAdminMenu.ts has to be added here: StorePermissionsTest
 * scans those sources and fails on a name this list lacks.
 *
 * Sections reuse the names the definition migrations and the role form
 * already carry; `dashboard`, `seller`, `reports`, `marketing`, `website_setup`
 * and `staffs` are the only new ones, for sidebar modules no earlier section
 * covered. `RoleController::REMOVED_ADDON_SECTIONS` stay out.
 */
final class StorePermissions
{
    private const string GUARD = 'store';

    /**
     * Section => permission names.
     *
     * @var array<string, list<string>>
     */
    private const array SECTIONS = [
        'dashboard' => [
            'admin_dashboard',
        ],
        'product' => [
            'add_brand',
            'add_digital_product',
            'add_new_product',
            'add_product_category',
            'delete_brand',
            'delete_color',
            'delete_digital_product',
            'delete_product_attribute',
            'delete_product_attribute_value',
            'delete_product_category',
            'download_digital_product',
            'edit_brand',
            'edit_color',
            'edit_digital_product',
            'edit_product_attribute',
            'edit_product_attribute_value',
            'edit_product_category',
            'product_bulk_export',
            'product_bulk_import',
            'product_delete',
            'product_duplicate',
            'product_edit',
            'publish_product_review',
            'show_all_products',
            'show_digital_products',
            'show_in_house_products',
            'show_seller_products',
            'view_all_brands',
            'view_colors',
            'view_product_attribute_values',
            'view_product_attributes',
            'view_product_categories',
            'view_product_reviews',
        ],
        'sales' => [
            'add_order',
            'delete_order',
            'edit_order',
            'edit_order_price',
            'order_configuration',
            'update_order_delivery_status',
            'update_order_notes',
            'update_order_payment_status',
            'update_order_tracking',
            'view_all_orders',
            'view_inhouse_orders',
            'view_order_details',
            'view_pickup_point_orders',
            'view_seller_orders',
        ],
        'customer' => [
            'add_notification',
            'ban_customer',
            'delete_classified_package',
            'delete_classified_product',
            'delete_customer',
            'delete_notification',
            'edit_classified_package',
            'edit_customer',
            'login_as_customer',
            'publish_classified_product',
            'view_all_customers',
            'view_classified_packages',
            'view_classified_products',
        ],
        'representatives' => [
            'add_representative_package',
            'approve_representative_payment',
            'assign_representative_package',
            'delete_representative_package',
            'delete_representative_visit',
            'edit_representative_package',
            'export_representative_payment',
            'view_representative_packages',
            'view_representative_payments',
            'view_representative_visits',
        ],
        'seller' => [
            'ban_seller',
            'delete_seller',
            'edit_seller',
            'login_as_seller',
            'pay_to_seller',
            'seller_commission_configuration',
            'seller_payment_history',
            'seller_verification_form_configuration',
            'view_all_seller',
            'view_seller_payout_requests',
            'view_seller_profile',
        ],
        'trips' => [
            'add_trip',
            'delete_trip',
            'edit_trip',
            'view_all_trips',
        ],
        'trucks' => [
            'add_truck',
            'delete_truck',
            'edit_truck',
            'view_all_trucks',
        ],
        'drivers' => [
            'add_driver',
            'delete_driver',
            'edit_driver',
            'view_all_drivers',
        ],
        'uploads' => [
            'delete_all_uploads',
            'delete_uploads',
            'upload_files',
            'view_uploads',
        ],
        'offers_catalog' => [
            'add_offers_catalog',
            'delete_offers_catalog',
            'view_offers_catalog',
        ],
        'blog' => [
            'add_blog',
            'add_blog_category',
            'delete_blog',
            'delete_blog_category',
            'edit_blog',
            'edit_blog_category',
            'publish_blog',
            'view_blog_categories',
            'view_blogs',
        ],
        'support' => [
            'delete_product_conversations',
            'reply_to_product_conversations',
            'reply_to_product_queries',
            'reply_to_support_tickets',
            'view_all_product_conversations',
            'view_all_product_queries',
            'view_all_support_tickets',
        ],
        'reports' => [
            'commission_history_report',
            'in_house_product_sale_report',
            'product_wishlist_report',
            'products_stock_report',
            'seller_products_sale_report',
            'user_search_report',
            'wallet_transaction_report',
        ],
        'marketing' => [
            'add_coupon',
            'add_dynamic_popups',
            'add_flash_deal',
            'add_offer_deal',
            'delete_coupon',
            'delete_dynamic_popups',
            'delete_flash_deal',
            'delete_offer_deal',
            'delete_subscriber',
            'edit_coupon',
            'edit_dynamic_popups',
            'edit_flash_deal',
            'edit_offer_deal',
            'publish_dynamic_popups',
            'publish_flash_deal',
            'send_newsletter',
            'view_all_coupons',
            'view_all_dynamic_popups',
            'view_all_flash_deals',
            'view_all_offer_deals',
            'view_all_subscribers',
        ],
        'website_setup' => [
            'add_website_page',
            'delete_website_page',
            'edit_website_page',
            'footer_setup',
            'header_setup',
            'view_all_website_pages',
            'website_appearance',
        ],
        'setup_configurations' => [
            'analytics_tools_configuration',
            'currency_setup',
            'facebook_chat',
            'facebook_comment',
            'features_activation',
            'file_system_&_cache_configuration',
            'general_settings',
            'google_firebase_setting',
            'google_map_setting',
            'google_recaptcha_configuration',
            'language_setup',
            'manage_carriers',
            'manage_shipping_cities',
            'manage_shipping_states',
            'manage_zones',
            'payment_methods_configurations',
            'pickup_point_setup',
            'shipping_configuration',
            'shipping_country_setting',
            'smtp_settings',
            'social_media_logins',
            'vat_&_tax_setup',
        ],
        'staffs' => [
            'add_staff',
            'add_staff_role',
            'delete_staff',
            'delete_staff_role',
            'edit_staff',
            'edit_staff_role',
            'view_all_staffs',
            'view_staff_roles',
        ],
    ];

    /**
     * @return array<string, string> permission name => section
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::SECTIONS as $section => $names) {
            foreach ($names as $name) {
                $permissions[$name] = $section;
            }
        }

        return $permissions;
    }

    /** Application permission labels belong to files; custom records remain content. */
    public static function displayName(string $name): string
    {
        return array_key_exists($name, self::all())
            ? __('store_permissions.permissions.'.$name)
            : translate(Str::headline($name));
    }

    /** Application sections use the same canonical vocabulary as permission syncing. */
    public static function displaySection(string $section): string
    {
        return array_key_exists($section, self::SECTIONS)
            ? __('store_permissions.sections.'.$section)
            : translate(Str::headline($section));
    }

    /**
     * Insert the store permissions that are missing. Additive only: an
     * existing row keeps its section and every role grant, nothing is
     * renamed or deleted, no permission is given to any role, and the `web`
     * guard (the warehouse's own permissions) is never read or written.
     *
     * @return array{created: list<string>, existing: list<string>}
     */
    public static function sync(bool $dryRun = false): array
    {
        $table = config('permission.table_names.permissions');
        $defined = DB::table($table)->where('guard_name', self::GUARD)->pluck('name')->flip();
        $created = [];
        $existing = [];
        $rows = [];

        foreach (self::all() as $name => $section) {
            if ($defined->has($name)) {
                $existing[] = $name;

                continue;
            }

            $created[] = $name;
            $rows[] = [
                'name' => $name, 'guard_name' => self::GUARD, 'section' => $section,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        if (! $dryRun) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insertOrIgnore($chunk);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return ['created' => $created, 'existing' => $existing];
    }
}
