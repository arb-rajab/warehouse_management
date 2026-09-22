<?php

use Illuminate\Support\Facades\Schema;

function loadCreateUsersTableMigration(): object
{
    return require database_path('migrations/0001_01_01_000000_create_users_table.php');
}

test('down() drops the wms_ prefixed tables', function () {
    loadCreateUsersTableMigration()->down();

    expect(Schema::hasTable('wms_users'))->toBeFalse();
    expect(Schema::hasTable('wms_password_reset_tokens'))->toBeFalse();
    expect(Schema::hasTable('wms_sessions'))->toBeFalse();
});

test('down() falls back to the legacy names once a rename migration has un-prefixed them', function () {
    // Stands in for a full `migrate:rollback`, where
    // `rename_users_table_to_wms_users`'s `down()` runs first and renames
    // these tables back to their legacy names before this migration's own
    // `down()` gets a turn.
    Schema::rename('wms_users', 'users');
    Schema::rename('wms_password_reset_tokens', 'password_reset_tokens');
    Schema::rename('wms_sessions', 'sessions');

    loadCreateUsersTableMigration()->down();

    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse();
    expect(Schema::hasTable('sessions'))->toBeFalse();
    expect(Schema::hasTable('wms_users'))->toBeFalse();
});
