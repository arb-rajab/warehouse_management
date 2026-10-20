<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\StorePermissions;
use Illuminate\Support\Facades\DB;

test('store:sync-permissions creates the missing permissions, then reports zero created', function () {
    Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->delete();
    $total = count(StorePermissions::all());

    $this->artisan('store:sync-permissions')
        ->expectsOutputToContain('Created add_coupon (marketing)')
        ->expectsOutputToContain('Created view_blogs (blog)')
        ->expectsOutputToContain('2 created, '.($total - 2).' already existed')
        ->assertSuccessful();

    $this->artisan('store:sync-permissions')
        ->expectsOutputToContain('0 created, '.$total.' already existed')
        ->assertSuccessful();

    expect(Permission::query()->count())->toBe($total);
});

test('store:sync-permissions --dry-run lists what it would create and writes nothing', function () {
    Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->delete();
    $before = DB::table('wms_permissions')->orderBy('id')->get();

    $this->artisan('store:sync-permissions --dry-run')
        ->expectsOutputToContain('Would create add_coupon (marketing)')
        ->expectsOutputToContain('Would create view_blogs (blog)')
        ->expectsOutputToContain('Dry run, nothing written: 2 would be created')
        ->assertSuccessful();

    expect(DB::table('wms_permissions')->orderBy('id')->get())->toEqual($before)
        ->and(Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->count())->toBe(0);
});

test('store:sync-permissions never changes a section or grants a role', function () {
    $role = Role::findOrCreate('Kept clerk', 'store');
    $role->givePermissionTo('view_all_orders');
    Permission::query()->where('name', 'view_all_orders')->update(['section' => 'kept_section']);
    $grants = DB::table('wms_role_has_permissions')->get();

    $this->artisan('store:sync-permissions')->assertSuccessful();

    expect(Permission::query()->where('name', 'view_all_orders')->value('section'))->toBe('kept_section')
        ->and(DB::table('wms_role_has_permissions')->get())->toEqual($grants);
});
