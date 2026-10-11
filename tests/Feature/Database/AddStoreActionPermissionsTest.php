<?php

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as WebRole;

dataset('store action permission migrations', [
    'order entry' => [
        '2026_10_10_035933_add_order_entry_permissions.php',
        ['add_order' => 'sales', 'edit_order' => 'sales', 'update_order_tracking' => 'sales', 'update_order_notes' => 'sales', 'edit_order_price' => 'sales'],
    ],
    'blog and support' => [
        '2026_10_11_235900_define_store_blog_and_support_action_permissions.php',
        ['publish_blog' => 'blog', 'reply_to_support_tickets' => 'support'],
    ],
]);

test('action definitions are store only and do not grant existing roles', function (string $file, array $sections) {
    $role = Role::findOrCreate('Existing action clerk', 'store');
    $staff = createStoreStaff();
    $staff->assignRole($role);
    $migration = require database_path('migrations/'.$file);
    $migration->up();

    foreach ($sections as $name => $section) {
        $this->assertDatabaseHas('wms_permissions', ['name' => $name, 'guard_name' => 'store', 'section' => $section]);
        $this->assertDatabaseMissing('wms_permissions', ['name' => $name, 'guard_name' => 'web']);
        expect($staff->hasPermissionTo($name, 'store'))->toBeFalse();
    }
    $this->assertDatabaseCount('wms_role_has_permissions', 0);
})->with('store action permission migrations');

test('action migration replay and rollback preserve definitions and store and web grants', function (string $file, array $sections) {
    $name = array_key_first($sections);
    $store = Permission::findByName($name, 'store');
    $store->forceFill(['section' => 'existing_section'])->save();
    $web = Permission::findOrCreate($name, 'web');
    $storeRole = Role::findOrCreate('Existing store action role', 'store');
    $webRole = WebRole::findOrCreate('Existing web action role', 'web');
    $storeRole->givePermissionTo($store);
    $webRole->givePermissionTo($web);
    $permissions = DB::table('wms_permissions')->orderBy('id')->get();
    $grants = DB::table('wms_role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get();

    $migration = require database_path('migrations/'.$file);
    $migration->up();
    $migration->up();
    $migration->down();

    expect(DB::table('wms_permissions')->orderBy('id')->get())->toEqual($permissions)
        ->and(DB::table('wms_role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get())->toEqual($grants)
        ->and($storeRole->fresh()->hasPermissionTo($name, 'store'))->toBeTrue()
        ->and($webRole->fresh()->hasPermissionTo($name, 'web'))->toBeTrue();
})->with('store action permission migrations');
