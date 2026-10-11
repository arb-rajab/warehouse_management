<?php

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('script permission migration grants only the store Super Admin and leaves WMS permissions intact', function () {
    DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'store')->delete();
    $store = Role::findOrCreate('Super Admin', 'store');
    $clerk = Role::findOrCreate('Clerk', 'store');
    $web = Role::findOrCreate('Super Admin', 'web');
    DB::table('wms_permissions')->insert(['name' => 'manage_website_scripts', 'guard_name' => 'web']);
    $migration = require database_path('migrations/2026_10_10_000001_add_manage_website_scripts_permission.php');
    $migration->up();
    $migration->up();
    $permission = DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'store')->first();
    expect($permission->section)->toBe('website_setup')->and(DB::table('wms_role_has_permissions')->where('permission_id', $permission->id)->pluck('role_id')->all())->toBe([$store->id]);
    $migration->down();
    expect(DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'store')->count())->toBe(0)->and(DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'web')->count())->toBe(1)->and(DB::table('wms_role_has_permissions')->where('permission_id', $permission->id)->count())->toBe(0);
});
