<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * edit_staff_role and edit_staff used to be equivalent to every permission but
 * Super Admin (role-grants audit G-3): a role editor could tick any permission
 * onto any role, a staff editor could give anyone any role, their own included,
 * and set the password of a more powerful account. Now no one grants a
 * permission they do not hold, and no one manages an account holding more than
 * they do (App\Support\StaffPrivileges).
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function escalationStaffForm(Role $role, array $overrides = []): array
{
    return [
        'name' => 'New Staff',
        'email' => 'new-staff@example.com',
        'mobile' => '0612345678',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
        'role_id' => $role->id,
        ...$overrides,
    ];
}

function escalationRole(string $name, string ...$permissions): Role
{
    $role = Role::findOrCreate($name, 'store');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'store'));
    }

    return $role;
}

function escalationStaff(string $roleName, string ...$permissions): Staff
{
    return createStaffRow(createStoreStaff(...$permissions), $roleName);
}

describe('roles', function () {
    test('the role form offers only the permissions the editor holds', function () {
        Permission::findOrCreate('pay_to_seller', 'store');
        actingAsStoreUser(createStoreStaff('add_staff_role', 'view_all_orders'));

        $this->get('/temp/admin/roles/create')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('permissionGroups', fn ($groups) => collect($groups)
                ->pluck('permissions')->flatten(1)->pluck('id')->sort()->values()->all()
                === Permission::query()->whereIn('name', ['add_staff_role', 'view_all_orders'])->pluck('id')->sort()->values()->all())
        );
    });

    test('a role is created with permissions the editor holds', function () {
        $held = Permission::findOrCreate('view_all_orders', 'store');
        actingAsStoreUser(createStoreStaff('add_staff_role', 'view_all_orders'));

        $this->post('/temp/admin/roles', ['name' => 'Clerk', 'permissions' => [$held->id]])->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'Clerk')->firstOrFail()->permissions->modelKeys())->toBe([$held->id]);
    });

    test('a role cannot be created with a permission the editor lacks', function () {
        $money = Permission::findOrCreate('pay_to_seller', 'store');
        actingAsStoreUser(createStoreStaff('add_staff_role', 'view_all_orders'));

        $this->post('/temp/admin/roles', ['name' => 'Clerk', 'permissions' => [$money->id]])->assertSessionHasErrors('permissions');

        expect(Role::where('name', 'Clerk')->exists())->toBeFalse();
    });

    test('a role editor cannot add a permission they lack to any role, their own included', function () {
        $money = Permission::findOrCreate('pay_to_seller', 'store');
        $own = escalationRole('Role editor', 'edit_staff_role');
        $editor = createStoreStaff('edit_staff_role');
        $editor->assignRole($own);
        actingAsStoreUser($editor);

        $this->put("/temp/admin/roles/{$own->id}", ['name' => 'Role editor', 'lang' => 'en', 'permissions' => [$own->permissions->first()->id, $money->id]])
            ->assertSessionHasErrors('permissions');

        expect($own->fresh()->permissions->pluck('name')->all())->toBe(['edit_staff_role']);
    });

    test('a role editor changes only the permissions they hold, and the rest of the role stays', function () {
        $orders = Permission::findOrCreate('view_all_orders', 'store');
        $money = Permission::findOrCreate('pay_to_seller', 'store');
        $target = escalationRole('Clerk', 'view_all_orders', 'pay_to_seller');
        actingAsStoreUser(createStoreStaff('edit_staff_role', 'view_all_orders'));

        // The form never offers the money permission; saving with nothing ticked drops the held one only.
        $this->put("/temp/admin/roles/{$target->id}", ['name' => 'Clerk', 'lang' => 'en'])->assertRedirect(route('roles.index'));

        expect($target->fresh()->permissions->modelKeys())->toBe([$money->id])
            ->and($target->fresh()->permissions->contains($orders))->toBeFalse();
    });

    test('a role editor can keep a permission they lack that the role already has', function () {
        $money = Permission::findOrCreate('pay_to_seller', 'store');
        $orders = Permission::findOrCreate('view_all_orders', 'store');
        $target = escalationRole('Clerk', 'pay_to_seller');
        actingAsStoreUser(createStoreStaff('edit_staff_role', 'view_all_orders'));

        $this->put("/temp/admin/roles/{$target->id}", ['name' => 'Clerk', 'lang' => 'en', 'permissions' => [$orders->id, $money->id]])
            ->assertRedirect(route('roles.index'));

        expect($target->fresh()->permissions->pluck('id')->sort()->values()->all())->toBe(collect([$money->id, $orders->id])->sort()->values()->all());
    });

    test('the Super Admin can still grant any permission', function () {
        $money = Permission::findOrCreate('pay_to_seller', 'store');
        actingAsStoreUser(createStoreAdmin());

        $this->post('/temp/admin/roles', ['name' => 'Cashier', 'permissions' => [$money->id]])->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'Cashier')->firstOrFail()->permissions->modelKeys())->toBe([$money->id]);
    });
});

describe('staff', function () {
    test('the staff form offers only roles that grant nothing beyond the editor\'s permissions', function () {
        $within = escalationRole('Within', 'view_all_orders');
        $beyond = escalationRole('Beyond', 'view_all_orders', 'pay_to_seller');
        actingAsStoreUser(createStoreStaff('add_staff', 'view_all_orders'));

        $this->get('/temp/admin/staffs/create')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('id')->all() === [$within->id])
        );
        expect($beyond->id)->not->toBe($within->id);
    });

    test('a staff account cannot be created with a role beyond the editor\'s permissions', function () {
        $beyond = escalationRole('Beyond', 'pay_to_seller');
        actingAsStoreUser(createStoreStaff('add_staff'));

        $this->post('/temp/admin/staffs', escalationStaffForm($beyond))->assertSessionHasErrors('role_id');

        expect(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('a staff account is created with a role within the editor\'s permissions', function () {
        $within = escalationRole('Within', 'view_all_orders');
        actingAsStoreUser(createStoreStaff('add_staff', 'view_all_orders'));

        $this->post('/temp/admin/staffs', escalationStaffForm($within))->assertRedirect(route('staffs.index'));

        expect(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeTrue();
    });

    test('a staff editor cannot change their own role, even to one within their permissions', function () {
        $other = escalationRole('Other', 'edit_staff');
        $mine = createStaffRow($editor = createStoreStaff('edit_staff'), 'Clerk');
        actingAsStoreUser($editor);

        $this->put("/temp/admin/staffs/{$mine->id}", escalationStaffForm($other, ['email' => $editor->email]))->assertSessionHasErrors('role_id');

        expect($mine->fresh()->role_id)->toBe($mine->role_id)
            ->and($editor->fresh()->roles->pluck('name')->all())->toBe(['Clerk']);
    });

    test('a staff editor can still update their own details with their role unchanged', function () {
        $mine = createStaffRow($editor = createStoreStaff('edit_staff'), 'Clerk');
        actingAsStoreUser($editor);

        $this->put("/temp/admin/staffs/{$mine->id}", escalationStaffForm($mine->role, ['email' => $editor->email, 'name' => 'Renamed']))
            ->assertRedirect(route('staffs.index'));

        expect($editor->fresh()->name)->toBe('Renamed');
    });

    test('a staff editor cannot give another account a role beyond their permissions', function () {
        $beyond = escalationRole('Beyond', 'pay_to_seller');
        $target = escalationStaff('Clerk');
        actingAsStoreUser(createStoreStaff('edit_staff'));

        $this->put("/temp/admin/staffs/{$target->id}", escalationStaffForm($beyond, ['email' => $target->user->email]))->assertSessionHasErrors('role_id');

        expect($target->fresh()->role_id)->toBe($target->role_id);
    });

    test('a staff editor can give a lower account a role within their permissions', function () {
        $within = escalationRole('Within', 'edit_staff');
        $target = escalationStaff('Clerk');
        actingAsStoreUser(createStoreStaff('edit_staff'));

        $this->put("/temp/admin/staffs/{$target->id}", escalationStaffForm($within, ['email' => $target->user->email]))->assertRedirect(route('staffs.index'));

        expect($target->fresh()->role_id)->toBe($within->id);
    });

    test('an account holding more than the editor cannot be opened, changed or deleted', function () {
        $target = escalationStaff('Boss', 'edit_staff', 'pay_to_seller');
        $clerkRole = escalationRole('Clerk', 'edit_staff');
        actingAsStoreUser(createStoreStaff('edit_staff', 'delete_staff'));

        $this->get("/temp/admin/staffs/{$target->id}/edit")->assertForbidden();
        $this->put("/temp/admin/staffs/{$target->id}", escalationStaffForm($clerkRole, ['email' => $target->user->email, 'password' => 'another-long-password', 'password_confirmation' => 'another-long-password']))->assertForbidden();
        $this->delete("/temp/admin/staffs/{$target->id}")->assertForbidden();

        $hash = $target->user->fresh()->password;
        expect($target->fresh())->not->toBeNull()
            ->and($target->fresh()->role_id)->toBe($target->role_id)
            ->and($target->user->fresh()->password)->toBe($hash);
    });

    test('an account holding no more than the editor can be opened', function () {
        $target = escalationStaff('Peer', 'edit_staff');
        actingAsStoreUser(createStoreStaff('edit_staff', 'view_all_orders'));

        $this->get("/temp/admin/staffs/{$target->id}/edit")->assertOk();
    });

    test('the Super Admin can give any role and manage any other account', function () {
        $beyond = escalationRole('Beyond', 'pay_to_seller');
        $target = escalationStaff('Boss', 'edit_staff', 'pay_to_seller');
        actingAsStoreUser(createStoreAdmin());

        $this->get("/temp/admin/staffs/{$target->id}/edit")->assertOk();
        $this->put("/temp/admin/staffs/{$target->id}", escalationStaffForm($beyond, ['email' => $target->user->email]))->assertRedirect(route('staffs.index'));

        expect($target->fresh()->role_id)->toBe($beyond->id);
    });
});
