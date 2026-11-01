<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function defineProductFieldSourcesPermissionMigration(): Migration
{
    return require collect(glob(database_path('migrations/*_define_product_field_sources_permission.php')))->sole();
}

test('the migration defines the product data sources permission in the setup section without granting it', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->where('name', 'product_field_sources')->delete();
    $role = Role::findOrCreate('Existing setup clerk', 'store');
    $grantsBefore = DB::table('wms_role_has_permissions')->count();

    defineProductFieldSourcesPermissionMigration()->up();

    expect(Permission::query()->where('name', 'product_field_sources')->sole()->getAttribute('section'))->toBe('setup_configurations')
        ->and(DB::table('wms_role_has_permissions')->count())->toBe($grantsBefore)
        ->and($role->fresh()->hasPermissionTo('product_field_sources'))->toBeFalse();
});

test('the migration is idempotent and its rollback keeps the permission and its grants', function () {
    $permission = Permission::findByName('product_field_sources', 'store');
    $role = Role::findOrCreate('Existing data source role', 'store');
    $role->givePermissionTo($permission);

    defineProductFieldSourcesPermissionMigration()->up();
    defineProductFieldSourcesPermissionMigration()->down();

    expect(Permission::query()->where('name', 'product_field_sources')->count())->toBe(1)
        ->and($role->fresh()->hasPermissionTo('product_field_sources'))->toBeTrue();
});
