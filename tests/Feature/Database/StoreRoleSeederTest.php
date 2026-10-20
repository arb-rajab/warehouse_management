<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\StorePermissions;
use Database\Seeders\StoreRoleSeeder;
use Illuminate\Support\Facades\DB;

test('the store role seeder defines every store permission, and in testing still creates only Super Admin and Clerk, with no grants', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();

    (new StoreRoleSeeder)->run();
    (new StoreRoleSeeder)->run();

    expect(Permission::query()->pluck('section', 'name')->all())->toEqual(StorePermissions::all())
        ->and(Role::query()->orderBy('name')->pluck('name')->all())->toBe(['Clerk', 'Super Admin'])
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0);
});
