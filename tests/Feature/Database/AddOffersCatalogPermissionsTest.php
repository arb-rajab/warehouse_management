<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

function offersCatalogPermissionMigration(): object
{
    return require glob(database_path('migrations/*_add_offers_catalog_permissions.php'))[0];
}

test('offers catalogue permission rows belong only to the store guard without granting any role', function () {
    $permissions = Permission::query()->where('section', 'offers_catalog')->orderBy('name')->get(['name', 'guard_name']);
    expect($permissions->toArray())->toBe([
        ['name' => 'add_offers_catalog', 'guard_name' => 'store'],
        ['name' => 'delete_offers_catalog', 'guard_name' => 'store'],
        ['name' => 'view_offers_catalog', 'guard_name' => 'store'],
    ]);
    expect(DB::table(config('permission.table_names.role_has_permissions'))->count())->toBe(0);
});

test('permission migration is idempotent and preserves existing store rows web rows and grants', function () {
    $existing = Permission::findByName('view_offers_catalog', 'store');
    $existing->section = 'production_section';
    $existing->save();
    $role = Role::findOrCreate('Catalogue reader', 'store');
    $role->givePermissionTo($existing);
    $webId = DB::table(config('permission.table_names.permissions'))->insertGetId([
        'name' => 'view_offers_catalog', 'guard_name' => 'web', 'section' => 'wms',
    ]);
    offersCatalogPermissionMigration()->up();
    offersCatalogPermissionMigration()->up();
    offersCatalogPermissionMigration()->down();
    expect($existing->fresh()->section)->toBe('production_section')
        ->and(Permission::query()->whereIn('name', ['view_offers_catalog', 'add_offers_catalog', 'delete_offers_catalog'])->count())->toBe(3)
        ->and($role->fresh()->hasPermissionTo('view_offers_catalog'))->toBeTrue()
        ->and(DB::table(config('permission.table_names.permissions'))->where('id', $webId)->value('section'))->toBe('wms');
});
