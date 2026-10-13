<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

test('integration action permissions do not automatically authorize staff or existing roles', function () {
    $role = Role::findOrCreate('Existing store clerk', 'store');
    $staff = createStoreStaff();
    $staff->assignRole($role);
    $names = ['view_uploads', 'upload_files', 'delete_uploads', 'delete_all_uploads', 'publish_blog', 'reply_to_support_tickets'];
    $permissions = Permission::query()->where('guard_name', 'store')->whereIn('name', $names)->get();
    expect($permissions)->toHaveCount(count($names));
    foreach ($names as $name) {
        expect($staff->hasPermissionTo($name, 'store'))->toBeFalse();
    }
    expect(DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $role->id)->count())->toBe(0);
});
