<?php

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

test('logistics permissions exist only in the store guard without automatic staff grants', function () {
    $sections = [
        'trips' => ['view_all_trips', 'add_trip', 'edit_trip', 'delete_trip'],
        'trucks' => ['view_all_trucks', 'add_truck', 'edit_truck', 'delete_truck'],
        'drivers' => ['view_all_drivers', 'add_driver', 'edit_driver', 'delete_driver'],
        'representatives' => ['view_representative_visits', 'delete_representative_visit'],
        'setup_configurations' => ['google_map_setting'],
    ];

    foreach ($sections as $section => $names) {
        foreach ($names as $name) {
            $this->assertDatabaseHas('wms_permissions', ['name' => $name, 'guard_name' => 'store', 'section' => $section]);
            $this->assertDatabaseMissing('wms_permissions', ['name' => $name, 'guard_name' => 'web']);
        }
    }

    $this->assertDatabaseCount('wms_role_has_permissions', 0);
});

test('rerunning and rolling back the permission migration preserves existing rows and grants', function () {
    $store = Permission::findByName('edit_trip', 'store');
    $store->forceFill(['section' => 'existing_section'])->save();
    $web = Permission::findOrCreate('edit_trip', 'web');
    $role = Role::findOrCreate('Logistics', 'store');
    $role->givePermissionTo($store);
    $before = DB::table('wms_permissions')->count();

    $migration = require database_path('migrations/2026_10_09_230302_add_store_logistics_permissions.php');
    $migration->up();
    $migration->down();

    expect(DB::table('wms_permissions')->count())->toBe($before)
        ->and($store->fresh()->section)->toBe('existing_section')
        ->and($web->fresh()->guard_name)->toBe('web')
        ->and($role->fresh()->hasPermissionTo('edit_trip'))->toBeTrue();
});
