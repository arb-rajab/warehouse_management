<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The store back office's staff roles (/temp/admin/roles), moved from Blade
 * to Inertia with the staff module (.ai/rules/store-backoffice-migration.md
 * §3). The translation and permission-section cases came over from
 * LegacyAdminPanelTest, and the paging case from LegacyPaginationTest.
 */

/**
 * A store language a role name can be translated into.
 */
function addStoreLanguage(string $code, string $name, bool $rtl = false): void
{
    DB::table('languages')->insert(['code' => $code, 'name' => $name, 'rtl' => (int) $rtl]);
}

describe('index', function () {
    test('the role list can be rendered', function () {
        actingAsStoreUser(createStoreStaff('view_staff_roles'));

        $this->get('/temp/admin/roles')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Roles/Index')
        );
    });

    test('the role list returns each role in full, named in the current language', function () {
        actingAsStoreUser(createStoreStaff('view_staff_roles'));
        $role = Role::findOrCreate('Clerk', 'store');
        DB::table('role_translations')->insert(['role_id' => $role->id, 'lang' => 'en', 'name' => 'Shop clerk']);

        $this->get('/temp/admin/roles')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('filters', ['per_page' => 20])
                ->has('roles.data', 1)
                ->has('roles.data.0', fn (Assert $listed) => $listed
                    ->where('id', $role->id)
                    ->where('name', 'Shop clerk'))
        );
    });

    test('the role list pages, and leaves out the Super Admin and warehouse roles', function () {
        $warehouseRole = SpatieRole::findOrCreate('admin', 'web');
        $ids = collect(range(1, 23))->map(fn (int $number) => Role::findOrCreate("Store Role $number", 'store')->id);
        $admin = createStoreAdmin();
        $superAdminRole = Role::findByName(Role::SUPER_ADMIN, 'store');
        actingAsStoreUser($admin);

        $firstPage = $this->get('/temp/admin/roles')->assertOk()->inertiaProps('roles');
        $secondPage = $this->get('/temp/admin/roles?page=2')->assertOk()->inertiaProps('roles');
        $listed = collect([...$firstPage['data'], ...$secondPage['data']])->pluck('id');

        expect($firstPage['data'])->toHaveCount(20)
            ->and($firstPage['meta']['total'])->toBe(23)
            ->and($listed->all())->toBe($ids->all())
            ->and($listed)->not->toContain($warehouseRole->id)
            ->and($listed)->not->toContain($superAdminRole->id);
    });

    test('the role list refuses a caller without the permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/roles'), $caller);
    })->with('refused store admin callers');
});

describe('create', function () {
    test('the add-role form can be rendered with the permissions grouped by section', function () {
        // This payload fixture owns its permission groups.
        Permission::query()->whereIn('section', ['customer', 'representatives'])->delete();
        $approve = Permission::create(['name' => 'approve_bulk_price_changes', 'section' => 'price_changes']);
        $unsorted = Permission::create(['name' => 'export_everything']);
        Permission::create(['name' => 'add_auction_product', 'section' => 'auction']);
        SpatiePermission::create(['name' => 'warehouse_only', 'guard_name' => 'web', 'section' => 'price_changes']);
        actingAsStoreUser(createStoreStaff('add_staff_role'));
        $ownPermission = Permission::findByName('add_staff_role', 'store');

        $this->get('/temp/admin/roles/create')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Roles/Create')
                ->where('permissionGroups', [
                    [
                        'section' => 'price_changes',
                        'label' => 'Price Changes',
                        'permissions' => [['id' => $approve->id, 'label' => 'Approve Bulk Price Changes']],
                    ],
                    [
                        'section' => '',
                        'label' => '',
                        'permissions' => [
                            ['id' => $unsorted->id, 'label' => 'Export Everything'],
                            ['id' => $ownPermission->id, 'label' => 'Add Staff Role'],
                        ],
                    ],
                ])
        );
    });

    test('the add-role form refuses a caller without the permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/roles/create'), $caller);
    })->with('refused store admin callers');
});

describe('store', function () {
    test('a role is created on the store guard with its permissions and default-language translation', function () {
        $permission = Permission::create(['name' => 'view_all_orders', 'section' => 'order']);
        actingAsStoreUser(createStoreStaff('add_staff_role'));

        $this->post('/temp/admin/roles', ['name' => 'Clerk', 'permissions' => [(string) $permission->id]])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Clerk')->firstOrFail();
        expect($role->guard_name)->toBe('store')
            ->and($role->permissions->modelKeys())->toBe([$permission->id]);
        $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Clerk', 'lang' => 'en']);
    });

    test('a store role may share its name with a warehouse role', function () {
        SpatieRole::findOrCreate('admin', 'web');
        actingAsStoreUser(createStoreStaff('add_staff_role'));

        $this->post('/temp/admin/roles', ['name' => 'admin'])->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
    });

    test('creating a role validates the name and the permissions', function () {
        Role::findOrCreate('Clerk', 'store');
        $warehousePermission = SpatiePermission::create(['name' => 'warehouse_only', 'guard_name' => 'web']);
        actingAsStoreUser(createStoreStaff('add_staff_role'));

        $this->post('/temp/admin/roles', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/temp/admin/roles', ['name' => 'Clerk'])->assertSessionHasErrors('name');
        $this->post('/temp/admin/roles', ['name' => str_repeat('a', 51)])->assertSessionHasErrors('name');
        $this->post('/temp/admin/roles', ['name' => 'Manager', 'permissions' => [$warehousePermission->id]])
            ->assertSessionHasErrors('permissions.0');
        $this->post('/temp/admin/roles', ['name' => 'Manager', 'permissions' => 'all'])->assertSessionHasErrors('permissions');

        expect(Role::where('name', 'Manager')->exists())->toBeFalse();
        $this->assertDatabaseCount('role_translations', 0);
    });

    test('creating a role refuses a caller without the permission, and creates nothing', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->post('/temp/admin/roles', ['name' => 'Nope']), $caller);

        $this->assertDatabaseMissing('wms_roles', ['name' => 'Nope']);
        $this->assertDatabaseCount('role_translations', 0);
    })->with('refused store admin callers');
});

describe('edit', function () {
    test('the edit-role form can be rendered in the default language', function () {
        // This payload fixture owns its permission groups.
        Permission::query()->whereIn('section', ['customer', 'representatives'])->delete();
        $permission = Permission::create(['name' => 'view_all_orders', 'section' => 'order']);
        $role = Role::findOrCreate('Clerk', 'store');
        $role->givePermissionTo($permission);
        addStoreLanguage('en', 'English');
        addStoreLanguage('ar', 'Arabic', rtl: true);
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->get("/temp/admin/roles/{$role->id}/edit")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Roles/Edit')
                ->where('role', ['id' => $role->id, 'name' => 'Clerk'])
                ->where('lang', 'en')
                ->where('name', 'Clerk')
                ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
                ->where('permissionIds', [$permission->id])
                ->has('permissionGroups', 2)
        );
    });

    test("the edit-role form shows the role's name in the language asked for", function () {
        $role = Role::findOrCreate('Clerk', 'store');
        addStoreLanguage('ar', 'Arabic', rtl: true);
        DB::table('role_translations')->insert(['role_id' => $role->id, 'lang' => 'ar', 'name' => 'كاتب']);
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->get("/temp/admin/roles/{$role->id}/edit?lang=ar")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('lang', 'ar')->where('name', 'كاتب')
        );
        $this->get("/temp/admin/roles/{$role->id}/edit?lang=xx")->assertSessionHasErrors('lang');
    });

    test('the Super Admin role cannot be opened for editing', function () {
        actingAsStoreUser(createStoreStaff('edit_staff_role'));
        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, 'store');

        $this->get("/temp/admin/roles/{$superAdmin->id}/edit")->assertForbidden();
    });

    test('editing a role that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->get('/temp/admin/roles/999999/edit')->assertNotFound();
    });

    test('the edit-role form refuses a caller without the permission', function (string $caller) {
        $role = Role::findOrCreate('Clerk', 'store');
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get("/temp/admin/roles/{$role->id}/edit"), $caller);
    })->with('refused store admin callers');
});

describe('update', function () {
    test('renaming a role in the default language renames the role and syncs its permissions', function () {
        $kept = Permission::create(['name' => 'view_all_orders', 'section' => 'order']);
        $dropped = Permission::create(['name' => 'delete_order', 'section' => 'order']);
        $role = Role::findOrCreate('Clerk', 'store');
        $role->givePermissionTo([$kept, $dropped]);
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Senior Clerk', 'lang' => 'en', 'permissions' => [$kept->id]])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Senior Clerk']);
        $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Senior Clerk', 'lang' => 'en']);
        expect($role->fresh()->permissions->modelKeys())->toBe([$kept->id]);
    });

    test('renaming a role in another language leaves the role name alone', function () {
        $role = Role::findOrCreate('Clerk', 'store');
        addStoreLanguage('fr', 'French');
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Employé', 'lang' => 'fr'])->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Clerk']);
        $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Employé', 'lang' => 'fr']);
    });

    test("a role's name must stay unique in the default language, though a translation may repeat another role's", function () {
        Role::findOrCreate('Manager', 'store');
        $role = Role::findOrCreate('Clerk', 'store');
        addStoreLanguage('fr', 'French');
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Manager', 'lang' => 'en'])->assertSessionHasErrors('name');
        $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Clerk', 'lang' => 'xx'])->assertSessionHasErrors('lang');
        $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Manager', 'lang' => 'fr'])->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Clerk']);
    });

    test('the Super Admin role cannot be renamed or given permissions', function () {
        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, 'store');
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->put("/temp/admin/roles/{$superAdmin->id}", ['name' => 'Owner', 'lang' => 'en'])->assertForbidden();

        $this->assertDatabaseHas('wms_roles', ['id' => $superAdmin->id, 'name' => Role::SUPER_ADMIN]);
    });

    test('updating a role that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('edit_staff_role'));

        $this->put('/temp/admin/roles/999999', ['name' => 'Ghost', 'lang' => 'en'])->assertNotFound();
    });

    test('updating a role refuses a caller without the permission, and changes nothing', function (string $caller) {
        $role = Role::findOrCreate('Clerk', 'store');
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->put("/temp/admin/roles/{$role->id}", ['name' => 'Hijacked', 'lang' => 'en']), $caller);

        $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Clerk']);
        $this->assertDatabaseCount('role_translations', 0);
    })->with('refused store admin callers');
});

describe('destroy', function () {
    test('deleting a role removes it and its translations, back on the same page', function () {
        $role = Role::findOrCreate('Clerk', 'store');
        $other = Role::findOrCreate('Manager', 'store');
        DB::table('role_translations')->insert([
            ['role_id' => $role->id, 'lang' => 'en', 'name' => 'Clerk'],
            ['role_id' => $other->id, 'lang' => 'en', 'name' => 'Manager'],
        ]);
        actingAsStoreUser(createStoreStaff('delete_staff_role'));

        $this->delete("/temp/admin/roles/{$role->id}?page=2")->assertRedirect(route('roles.index', ['page' => 2]));

        $this->assertDatabaseMissing('wms_roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_translations', ['role_id' => $role->id]);
        $this->assertDatabaseHas('role_translations', ['role_id' => $other->id, 'name' => 'Manager']);
    });

    test('the Super Admin role cannot be deleted', function () {
        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, 'store');
        actingAsStoreUser(createStoreStaff('delete_staff_role'));

        $this->delete("/temp/admin/roles/{$superAdmin->id}")->assertForbidden();

        $this->assertDatabaseHas('wms_roles', ['id' => $superAdmin->id]);
    });

    test('deleting a role that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('delete_staff_role'));

        $this->delete('/temp/admin/roles/999999')->assertNotFound();
    });

    test('deleting a role refuses a caller without the permission, and deletes nothing', function (string $caller) {
        $role = Role::findOrCreate('Clerk', 'store');
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->delete("/temp/admin/roles/{$role->id}"), $caller);

        $this->assertDatabaseHas('wms_roles', ['id' => $role->id]);
    })->with('refused store admin callers');
});
