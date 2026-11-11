<?php

use App\Models\Permission;
use App\Models\Role;

test('withSensitiveFlag() marks the roles carrying a sensitive permission, and only those', function () {
    $sensitive = Role::findOrCreate('Mail admin', 'store');
    $sensitive->givePermissionTo(Permission::findOrCreate('smtp_settings', 'store'), Permission::findOrCreate('view_all_orders', 'store'));
    $plain = Role::findOrCreate('Clerk', 'store');
    $plain->givePermissionTo('view_all_orders');
    Role::findOrCreate('Nothing', 'store');

    $flags = Role::query()->withSensitiveFlag()->get()->mapWithKeys(fn (Role $role) => [$role->name => (bool) $role->grants_sensitive]);

    expect($flags->all())->toEqual(['Mail admin' => true, 'Clerk' => false, 'Nothing' => false]);
});
