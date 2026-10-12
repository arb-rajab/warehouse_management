<?php

use Illuminate\Support\Facades\Schema;

function loadCreatePermissionTablesMigration(): object
{
    return require database_path('migrations/2026_08_09_114507_create_permission_tables.php');
}

test('down() drops the wms_ prefixed tables', function () {
    loadCreatePermissionTablesMigration()->down();

    foreach (config('permission.table_names') as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

test('down() falls back to the legacy names once a rename migration has un-prefixed them', function () {
    // Order matters: the pivot tables carry foreign keys onto roles/permissions.
    Schema::rename('wms_role_has_permissions', 'role_has_permissions');
    Schema::rename('wms_model_has_roles', 'model_has_roles');
    Schema::rename('wms_model_has_permissions', 'model_has_permissions');
    Schema::rename('wms_roles', 'roles');
    Schema::rename('wms_permissions', 'permissions');

    loadCreatePermissionTablesMigration()->down();

    expect(Schema::hasTable('role_has_permissions'))->toBeFalse();
    expect(Schema::hasTable('model_has_roles'))->toBeFalse();
    expect(Schema::hasTable('model_has_permissions'))->toBeFalse();
    expect(Schema::hasTable('roles'))->toBeFalse();
    expect(Schema::hasTable('permissions'))->toBeFalse();
    expect(Schema::hasTable('wms_roles'))->toBeFalse();
});
