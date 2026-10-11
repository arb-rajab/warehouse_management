<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function customerAdministrationPermissionMigration(): Migration
{
    return require database_path('migrations/2026_10_08_102608_add_customer_administration_permissions.php');
}

test('customer and representative permissions are store-only and grouped for the role editor', function () {
    $names = [
        'edit_customer' => 'customer',
        'view_representative_packages' => 'representatives',
        'add_representative_package' => 'representatives',
        'edit_representative_package' => 'representatives',
        'delete_representative_package' => 'representatives',
        'assign_representative_package' => 'representatives',
        'view_representative_payments' => 'representatives',
        'approve_representative_payment' => 'representatives',
        'export_representative_payment' => 'representatives',
        'view_representative_visits' => 'representatives',
        'delete_representative_visit' => 'representatives',
    ];
    foreach ($names as $name => $section) {
        $this->assertDatabaseHas('wms_permissions', ['name' => $name, 'guard_name' => 'store', 'section' => $section]);
        $this->assertDatabaseMissing('wms_permissions', ['name' => $name, 'guard_name' => 'web']);
    }
});

test('permission installation is idempotent and never grants access to existing roles', function () {
    $role = Role::findOrCreate('Existing Clerk', 'store');
    $permission = Permission::findByName('edit_customer', 'store');
    $role->givePermissionTo($permission);
    $id = $permission->id;
    $grants = DB::table('wms_role_has_permissions')->get()->toArray();

    customerAdministrationPermissionMigration()->up();
    expect(Permission::findByName('edit_customer', 'store')->id)->toBe($id);
    expect(DB::table('wms_role_has_permissions')->get()->toArray())->toEqual($grants);
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['edit_customer']);

    customerAdministrationPermissionMigration()->down();
    expect(Permission::findByName('edit_customer', 'store')->id)->toBe($id);
    expect(DB::table('wms_role_has_permissions')->get()->toArray())->toEqual($grants);
});
