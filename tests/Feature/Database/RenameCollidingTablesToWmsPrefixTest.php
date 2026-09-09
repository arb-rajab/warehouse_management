<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadRenameCollidingTablesMigration(): object
{
    return require database_path('migrations/2026_09_08_000000_rename_colliding_tables_to_wms_prefix.php');
}

/**
 * Legacy name => current name, mirroring the migration's own map so a table
 * added to (or dropped from) it without updating this test fails loudly.
 *
 * @return array<string, string>
 */
function collidingTableRenames(): array
{
    return [
        'sessions' => 'wms_sessions',
        'password_reset_tokens' => 'wms_password_reset_tokens',
        'cache' => 'wms_cache',
        'cache_locks' => 'wms_cache_locks',
        'jobs' => 'wms_jobs',
        'job_batches' => 'wms_job_batches',
        'failed_jobs' => 'wms_failed_jobs',
        'personal_access_tokens' => 'wms_personal_access_tokens',
        'roles' => 'wms_roles',
        'permissions' => 'wms_permissions',
        'model_has_permissions' => 'wms_model_has_permissions',
        'model_has_roles' => 'wms_model_has_roles',
        'role_has_permissions' => 'wms_role_has_permissions',
    ];
}

test('every colliding table exists only under its wms_ name after migrating', function () {
    foreach (collidingTableRenames() as $legacy => $current) {
        expect(Schema::hasTable($current))->toBeTrue("expected {$current} to exist");
        expect(Schema::hasTable($legacy))->toBeFalse("expected {$legacy} not to exist");
    }
});

test('the migration is a no-op on a fresh install, where the wms_ tables already exist', function () {
    loadRenameCollidingTablesMigration()->up();

    foreach (collidingTableRenames() as $legacy => $current) {
        expect(Schema::hasTable($current))->toBeTrue("expected {$current} to survive");
        expect(Schema::hasTable($legacy))->toBeFalse("expected {$legacy} not to be created");
    }
});

test('the migration leaves another app\'s table of the same name alone', function () {
    // Stands in for the shared production database, where `roles` belongs to
    // the store app and `wms_roles` is this app's own table. Renaming the
    // store's table here would break that app, so the migration must not.
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('store_only_column');
    });
    DB::table('roles')->insert(['store_only_column' => 'owned by the store app']);
    DB::table('wms_roles')->insert([
        'name' => 'admin',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loadRenameCollidingTablesMigration()->up();

    expect(Schema::hasColumn('roles', 'store_only_column'))->toBeTrue();
    expect(DB::table('roles')->value('store_only_column'))->toBe('owned by the store app');
    expect(DB::table('wms_roles')->value('name'))->toBe('admin');

    Schema::drop('roles');
});

test('a legacy table this app does own is renamed, and its rows come with it', function () {
    // `wms_cache` stands in for any legacy-named table: it carries no foreign
    // keys, and the test suite runs the array cache store, so recreating it
    // under the old name affects nothing else.
    Schema::drop('wms_cache');
    Schema::create('cache', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->bigInteger('expiration')->index();
    });
    DB::table('cache')->insert(['key' => 'carried-over', 'value' => 'v', 'expiration' => 1]);

    loadRenameCollidingTablesMigration()->up();

    expect(Schema::hasTable('wms_cache'))->toBeTrue();
    expect(Schema::hasTable('cache'))->toBeFalse();
    expect(DB::table('wms_cache')->value('key'))->toBe('carried-over');
});

test('rows survive a down() and up() round trip', function () {
    DB::table('wms_cache')->insert(['key' => 'round-tripped', 'value' => 'v', 'expiration' => 1]);

    loadRenameCollidingTablesMigration()->down();

    expect(Schema::hasTable('cache'))->toBeTrue();
    expect(Schema::hasTable('wms_cache'))->toBeFalse();
    expect(DB::table('cache')->value('key'))->toBe('round-tripped');

    loadRenameCollidingTablesMigration()->up();

    expect(Schema::hasTable('wms_cache'))->toBeTrue();
    expect(Schema::hasTable('cache'))->toBeFalse();
    expect(DB::table('wms_cache')->value('key'))->toBe('round-tripped');
});
