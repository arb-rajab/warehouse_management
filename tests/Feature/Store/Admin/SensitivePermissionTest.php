<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Support\StorePermissions;
use Database\Seeders\StoreRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * `smtp_settings` chooses the mail server, so whoever holds it can read every
 * email the shop sends, password resets included. Only a Super Admin grants
 * it (StorePermissions::SENSITIVE, App\Support\StaffPrivileges); existing
 * grants are never revoked (.ai/rules/admin-env-overrides.md, "Security").
 */

function sensitiveRole(string $name, string ...$permissions): Role
{
    $role = Role::findOrCreate($name, 'store');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'store'));
    }

    return $role;
}

test('smtp_settings and payment_methods_configurations are the sensitive permissions and defined store permissions', function () {
    expect(StorePermissions::SENSITIVE)->toBe(['smtp_settings', 'payment_methods_configurations'])
        ->and(StorePermissions::isSensitive('smtp_settings'))->toBeTrue()
        ->and(StorePermissions::isSensitive('payment_methods_configurations'))->toBeTrue()
        ->and(StorePermissions::isSensitive('view_all_orders'))->toBeFalse()
        ->and(StorePermissions::all())->toHaveKey('smtp_settings')
        ->and(StorePermissions::all())->toHaveKey('payment_methods_configurations');
});

test('the role seeder grants no permission to any role but the Super Admin, which needs none', function () {
    (new StoreRoleSeeder)->run();

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['Clerk', Role::SUPER_ADMIN])
        ->and(Role::findByName('Clerk', 'store')->permissions)->toBeEmpty()
        ->and(Role::findByName(Role::SUPER_ADMIN, 'store')->permissions)->toBeEmpty();
});

describe('the role form', function () {
    test('a Super Admin is offered the sensitive permissions, marked sensitive, and nothing else is', function () {
        actingAsStoreUser(createStoreAdmin());
        $smtp = Permission::findByName('smtp_settings', 'store');
        $payments = Permission::findByName('payment_methods_configurations', 'store');

        $this->get(route('roles.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissionGroups', function ($groups) use ($smtp, $payments): bool {
                $permissions = collect($groups)->pluck('permissions')->flatten(1);

                return $permissions->firstWhere('id', $smtp->id)['sensitive'] === true
                    && $permissions->firstWhere('id', $payments->id)['sensitive'] === true
                    && $permissions->where('sensitive', true)->pluck('id')->sort()->values()->all() === collect([$smtp->id, $payments->id])->sort()->values()->all();
            }));
    });

    test('staff holding smtp_settings are not offered it, since only a Super Admin grants it', function () {
        $smtp = Permission::findOrCreate('smtp_settings', 'store');
        actingAsStoreUser(createStoreStaff('add_staff_role', 'smtp_settings', 'view_all_orders'));

        $this->get(route('roles.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissionGroups', fn ($groups) => collect($groups)->pluck('permissions')->flatten(1)->pluck('id')->sort()->values()->all()
                === Permission::query()->whereIn('name', ['add_staff_role', 'view_all_orders'])->pluck('id')->sort()->values()->all()
                && ! collect($groups)->pluck('permissions')->flatten(1)->contains('id', $smtp->id)));
    });

    test('staff holding smtp_settings cannot create a role with it', function () {
        $smtp = Permission::findOrCreate('smtp_settings', 'store');
        actingAsStoreUser(createStoreStaff('add_staff_role', 'smtp_settings'));

        $this->post(route('roles.store'), ['name' => 'Mail admin', 'permissions' => [$smtp->id]])
            ->assertSessionHasErrors(['permissions' => __('messages.role_permission_super_admin_only')]);

        expect(Role::query()->where('name', 'Mail admin')->exists())->toBeFalse();
    });

    test('staff holding smtp_settings cannot add it to an existing role', function () {
        $smtp = Permission::findOrCreate('smtp_settings', 'store');
        $orders = Permission::findOrCreate('view_all_orders', 'store');
        $role = sensitiveRole('Clerk', 'view_all_orders');
        actingAsStoreUser(createStoreStaff('edit_staff_role', 'smtp_settings', 'view_all_orders'));

        $this->put(route('roles.update', $role), ['name' => 'Clerk', 'lang' => 'en', 'permissions' => [$orders->id, $smtp->id]])
            ->assertSessionHasErrors(['permissions' => __('messages.role_permission_super_admin_only')]);

        expect($role->fresh()->permissions->pluck('name')->all())->toBe(['view_all_orders']);
    });

    test('staff holding smtp_settings cannot take it off a role either: an existing grant stays', function () {
        $role = sensitiveRole('Mail admin', 'smtp_settings', 'view_all_orders');
        actingAsStoreUser(createStoreStaff('edit_staff_role', 'smtp_settings', 'view_all_orders'));

        // The form does not offer smtp_settings, so it is not posted; the held permission is unticked.
        $this->put(route('roles.update', $role), ['name' => 'Mail admin', 'lang' => 'en'])->assertRedirect(route('roles.index'));

        expect($role->fresh()->permissions->pluck('name')->all())->toBe(['smtp_settings']);
    });

    test('a Super Admin can grant smtp_settings and take it off again', function () {
        $smtp = Permission::findOrCreate('smtp_settings', 'store');
        actingAsStoreUser(createStoreAdmin());

        $this->post(route('roles.store'), ['name' => 'Mail admin', 'permissions' => [$smtp->id]])->assertRedirect(route('roles.index'));
        $role = Role::findByName('Mail admin', 'store');
        expect($role->permissions->modelKeys())->toBe([$smtp->id]);

        $this->put(route('roles.update', $role), ['name' => 'Mail admin', 'lang' => 'en'])->assertRedirect(route('roles.index'));
        expect($role->fresh()->permissions)->toBeEmpty();
    });

    test('the roles list marks a role carrying a sensitive permission', function () {
        $sensitive = sensitiveRole('Mail admin', 'smtp_settings');
        $plain = sensitiveRole('Clerk', 'view_all_orders');
        actingAsStoreUser(createStoreAdmin());

        $this->get(route('roles.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('roles.data', fn ($roles) => collect($roles)->pluck('sensitive', 'id')->all() === [$plain->id => false, $sensitive->id => true]));
    });
});

describe('the staff form', function () {
    test('a role carrying smtp_settings is offered, marked sensitive, to a Super Admin only', function () {
        $sensitive = sensitiveRole('Mail admin', 'smtp_settings');
        $plain = sensitiveRole('Clerk', 'view_all_orders');

        actingAsStoreUser(createStoreAdmin());
        $this->get(route('staffs.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('roles', [
                ['id' => $plain->id, 'name' => 'Clerk', 'sensitive' => false],
                ['id' => $sensitive->id, 'name' => 'Mail admin', 'sensitive' => true],
            ]));

        actingAsStoreUser(createStoreStaff('add_staff', 'smtp_settings', 'view_all_orders'));
        $this->get(route('staffs.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('roles', [['id' => $plain->id, 'name' => 'Clerk', 'sensitive' => false]]));
    });

    test('staff holding smtp_settings cannot give a role carrying it to a new account', function () {
        $sensitive = sensitiveRole('Mail admin', 'smtp_settings');
        actingAsStoreUser(createStoreStaff('add_staff', 'smtp_settings'));

        $this->post(route('staffs.store'), [
            'name' => 'New Staff', 'email' => 'new-staff@example.com', 'mobile' => '0612345678',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'role_id' => $sensitive->id,
        ])->assertSessionHasErrors('role_id');

        expect(StoreUser::query()->where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('staff holding smtp_settings can still edit an account that already has such a role, keeping it', function () {
        $sensitive = sensitiveRole('Mail admin', 'smtp_settings');
        $target = createStaffRow(createStoreStaff(), 'Mail admin');
        actingAsStoreUser(createStoreStaff('edit_staff', 'smtp_settings'));

        $this->get(route('staffs.edit', $target))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->contains(fn (array $role) => $role['id'] === $sensitive->id && $role['sensitive'] === true)));

        $this->put(route('staffs.update', $target), [
            'name' => 'Renamed', 'email' => $target->user->email, 'mobile' => '0612345678', 'role_id' => $sensitive->id,
        ])->assertRedirect(route('staffs.index'));

        expect($target->user->fresh()->name)->toBe('Renamed')
            ->and($target->fresh()->role_id)->toBe($sensitive->id);
    });
});
