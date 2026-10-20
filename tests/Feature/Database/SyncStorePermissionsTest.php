<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\StorePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as WebPermission;

function syncStorePermissionsMigration(): Migration
{
    return require collect(glob(database_path('migrations/*_sync_store_permissions.php')))->sole();
}

test('the migration defines every store permission without granting any role', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();
    $role = Role::findOrCreate('Existing sync clerk', 'store');
    $staff = createStoreStaff();
    $staff->assignRole($role);

    syncStorePermissionsMigration()->up();

    expect(Permission::query()->pluck('section', 'name')->all())->toEqual(StorePermissions::all())
        ->and(DB::table('wms_permissions')->where('guard_name', 'web')->count())->toBe(0)
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0)
        ->and(DB::table('wms_model_has_permissions')->count())->toBe(0)
        ->and($staff->hasPermissionTo('view_all_orders', 'store'))->toBeFalse();
});

test('the migration replay and rollback preserve definitions, sections and store and web grants', function () {
    $store = Permission::findByName('view_all_orders', 'store');
    $store->forceFill(['section' => 'existing_section'])->save();
    $web = WebPermission::findOrCreate('view_all_orders', 'web');
    $storeRole = Role::findOrCreate('Existing store sync role', 'store');
    $webRole = Spatie\Permission\Models\Role::findOrCreate('Existing web sync role', 'web');
    $storeRole->givePermissionTo($store);
    $webRole->givePermissionTo($web);
    $permissions = DB::table('wms_permissions')->orderBy('id')->get();
    $grants = DB::table('wms_role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get();

    $migration = syncStorePermissionsMigration();
    $migration->up();
    $migration->up();
    $migration->down();

    expect(DB::table('wms_permissions')->orderBy('id')->get())->toEqual($permissions)
        ->and(DB::table('wms_role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get())->toEqual($grants)
        ->and($store->fresh()->section)->toBe('existing_section');
});
