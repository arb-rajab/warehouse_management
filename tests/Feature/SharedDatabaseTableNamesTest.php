<?php

use Illuminate\Support\Facades\Schema;

/**
 * Production runs this app and the store app against one MySQL database, so
 * every table this app owns carries a `wms_` prefix and only the genuinely
 * shared ones keep a bare name. These assertions guard the config half of that
 * arrangement: re-publishing a vendor config, or a package upgrade shipping a
 * new default, would silently point this app back at the store's tables. See
 * .ai/rules/shared-database.md.
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

test('the only bare-named tables are this app\'s own domain tables plus the shared ones', function () {
    // Everything without a `wms_` prefix is either a table whose name is
    // specific enough to this domain that the store app has nothing like it,
    // or one of the two tables genuinely shared with the store app —
    // `products` and the `uploads` its thumbnails point at. A new bare name
    // appearing here is a table that needs one of those two justifications
    // before it ships.
    $bareNamed = array_values(array_filter(
        Schema::getTableListing(schemaQualified: false),
        fn (string $name): bool => ! str_starts_with($name, 'wms_')
            && ! str_starts_with($name, 'sqlite_'),
    ));

    sort($bareNamed);

    expect($bareNamed)->toBe([
        'cell_status_log_flags',
        'cell_status_logs',
        'cell_verification_reports',
        'cell_verification_rounds',
        'cells',
        'mobile_app_version_requirements',
        'pallets',
        'products',
        'rows',
        'uploads',
    ]);
});
