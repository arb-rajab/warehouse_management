<?php

use Illuminate\Support\Facades\Schema;

/**
 * This app once shared one MySQL database with the store app, which is why
 * every table it owns carries a `wms_` prefix. The databases are separate now,
 * but those are the live table names in every existing database, so these
 * assertions still hold the line: re-publishing a vendor config, or a package
 * upgrade shipping a new default, would silently point this app at an
 * unprefixed table it has never migrated. See .ai/rules/shared-database.md.
 */
test('every framework table name this app owns is wms-prefixed', function () {
    expect(config('database.migrations.table'))->toBe('wms_migrations');
    expect(config('session.table'))->toBe('wms_sessions');
    expect(config('auth.passwords.users.table'))->toBe('wms_password_reset_tokens');
    expect(config('cache.stores.database.table'))->toBe('wms_cache');
    expect(config('cache.stores.database.lock_table'))->toBe('wms_cache_locks');
    expect(config('queue.connections.database.table'))->toBe('wms_jobs');
    expect(config('queue.batching.table'))->toBe('wms_job_batches');
    expect(config('queue.failed.table'))->toBe('wms_failed_jobs');
});

test('every spatie permission table name is wms-prefixed', function () {
    expect(config('permission.table_names'))->toBe([
        'roles' => 'wms_roles',
        'permissions' => 'wms_permissions',
        'model_has_permissions' => 'wms_model_has_permissions',
        'model_has_roles' => 'wms_model_has_roles',
        'role_has_permissions' => 'wms_role_has_permissions',
    ]);
});

test('the only bare-named tables are this app\'s own domain tables plus the shared and legacy-store ones', function () {
    // Everything without a `wms_` prefix is either a table whose name was
    // specific enough to this domain that the store app had nothing like it,
    // one of the tables this app shares with the legacy store app
    // (`products`, which this app has taken ownership of; the `uploads` its
    // thumbnails point at; and `product_barcodes`, which
    // Product::storeBarcodes() reads — none of which this app has taken
    // ownership of except `products` — see .ai/rules/shared-database.md),
    // or one of the legacy store's own tables
    // carried over verbatim from `shop.sql` during the schema-only merge (see
    // .ai/rules/legacy-albaraka-holland.md §3.1) — none of which collide with
    // any WMS table, which is exactly why they kept their bare names instead
    // of getting a `wms_`/`shop_` prefix. A new bare name appearing here is a
    // table that needs one of those justifications before it ships.
    $bareNamed = array_values(array_filter(
        Schema::getTableListing(schemaQualified: false),
        fn (string $name): bool => ! str_starts_with($name, 'wms_')
            && ! str_starts_with($name, 'sqlite_'),
    ));

    sort($bareNamed);

    expect($bareNamed)->toBe([
        'addons',
        'addresses',
        'app_translations',
        'attribute_category',
        'attribute_translations',
        'attribute_values',
        'attributes',
        'blog_categories',
        'blogs',
        'brand_translations',
        'brands',
        'business_settings',
        'carrier_range_prices',
        'carrier_ranges',
        'carriers',
        'carts',
        'categories',
        'category_translations',
        'cell_status_log_flags',
        'cell_status_logs',
        'cell_verification_reports',
        'cell_verification_round_row',
        'cell_verification_rounds',
        'cells',
        'cities',
        'city_translations',
        'colors',
        'combined_orders',
        'commission_histories',
        'conversations',
        'countries',
        'coupon_usages',
        'coupons',
        'currencies',
        'customer_package_payments',
        'customer_package_translations',
        'customer_packages',
        'customer_product_translations',
        'customer_products',
        'firebase_notifications',
        'flash_deal_products',
        'flash_deal_translations',
        'flash_deals',
        'home_categories',
        'languages',
        'messages',
        'mobile_app_version_requirements',
        'notifications',
        'order_details',
        'orders',
        'page_translations',
        'pages',
        'pallets',
        'password_resets',
        'payku_payments',
        'payku_transactions',
        'payments',
        'pickup_point_translations',
        'pickup_points',
        'product_barcodes',
        'product_queries',
        'product_stocks',
        'product_taxes',
        'product_translations',
        'products',
        'proxypay_payments',
        'reviews',
        'role_translations',
        'rows',
        'searches',
        'seller_withdraw_requests',
        'sellers',
        'shops',
        'staff',
        'states',
        'subscribers',
        'taxes',
        'ticket_replies',
        'tickets',
        'transactions',
        'translations',
        'uploads',
        'users',
        'wallets',
        'wishlists',
        'zones',
    ]);
});
