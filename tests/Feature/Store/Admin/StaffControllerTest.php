<?php

use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The store back office's staff screens (/temp/admin/staffs), the first
 * module moved from Blade to Inertia (.ai/rules/store-backoffice-migration.md
 * §3). The validation, Super Admin and self-deletion cases came over from
 * LegacyAdminOwnershipTest, where the Blade screens were pinned.
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeStaffForm(array $overrides = []): array
{
    return [
        'name' => 'New Staff',
        'email' => 'new-staff@example.com',
        'mobile' => '0612345678',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
        'role_id' => Role::findOrCreate('Clerk', 'store')->id,
        ...$overrides,
    ];
}

describe('index', function () {
    test('the staff list can be rendered', function () {
        actingAsStoreUser(createStoreStaff('view_all_staffs'));

        $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Staffs/Index')
        );
    });

    test('the staff list returns each member in full', function () {
        actingAsStoreUser(createStoreStaff('view_all_staffs'));
        $member = createStoreUser('staff', 'sam@example.com');
        $member->forceFill(['name' => 'Sam Staff', 'phone' => '0612345678'])->save();
        $row = createStaffRow($member);

        $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Staffs/Index')
                ->where('filters', ['per_page' => 20])
                ->has('staff.data', 1)
                ->has('staff.data.0', fn (Assert $staff) => $staff
                    ->where('id', $row->id)
                    ->where('user_id', $member->id)
                    ->where('name', 'Sam Staff')
                    ->where('email', 'sam@example.com')
                    ->where('phone', '0612345678')
                    ->where('is_super_admin', false)
                    ->has('role', fn (Assert $role) => $role
                        ->where('id', $row->role_id)
                        ->where('name', 'Clerk')))
        );
    });

    test('the staff list marks a Super Admin, names a role in the current language, and leaves out rows with no account', function () {
        actingAsStoreUser(createStoreStaff('view_all_staffs'));
        $superAdmin = createStaffRow(createStoreAdmin(), Role::SUPER_ADMIN);
        $translated = createStaffRow(createStoreUser('staff'), 'Clerk');
        DB::table('role_translations')->insert(['role_id' => $translated->role_id, 'lang' => 'en', 'name' => 'Shop clerk']);
        Staff::forceCreate(['user_id' => 999999, 'role_id' => $translated->role_id]);

        $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->has('staff.data', 2)
                ->where('staff.data.0.id', $superAdmin->id)
                ->where('staff.data.0.is_super_admin', true)
                ->where('staff.data.1.role.name', 'Shop clerk')
                ->where('staff.meta.total', 2)
        );
    });

    test('the staff list pages, twenty to a page by default', function () {
        actingAsStoreUser(createStoreStaff('view_all_staffs'));
        $ids = collect(range(1, 23))->map(fn () => createStaffRow(createStoreUser('staff'))->id);

        $firstPage = $this->get('/temp/admin/staffs')->assertOk()->inertiaProps('staff');
        $secondPage = $this->get('/temp/admin/staffs?page=2')->assertOk()->inertiaProps('staff');

        expect(collect($firstPage['data'])->pluck('id')->all())->toBe($ids->take(20)->all())
            ->and(collect($secondPage['data'])->pluck('id')->all())->toBe($ids->slice(20)->values()->all())
            ->and($firstPage['meta']['total'])->toBe(23);
    });

    test('the staff list honours an allowed page size and refuses any other', function () {
        actingAsStoreUser(createStoreStaff('view_all_staffs'));
        foreach (range(1, 12) as $number) {
            createStaffRow(createStoreUser('staff'));
        }

        $this->get('/temp/admin/staffs?per_page=10')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('staff.data', 10)->where('filters.per_page', 10)
        );
        $this->get('/temp/admin/staffs?per_page=7')->assertSessionHasErrors('per_page');
    });

    test('the staff list refuses a caller without the permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/staffs'), $caller);
    })->with('refused store admin callers');
});

describe('create', function () {
    test('the add-staff form can be rendered, offering every store role but Super Admin', function () {
        Role::findOrCreate(Role::SUPER_ADMIN, 'store');
        $clerk = Role::findOrCreate('Clerk', 'store');
        $manager = Role::findOrCreate('Manager', 'store');
        actingAsStoreUser(createStoreStaff('add_staff'));

        $this->get('/temp/admin/staffs/create')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Staffs/Create')
                ->where('roles', [
                    ['id' => $manager->id, 'name' => 'Manager'],
                    ['id' => $clerk->id, 'name' => 'Clerk'],
                ])
        );
    });

    test('the add-staff form refuses a caller without the permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get('/temp/admin/staffs/create'), $caller);
    })->with('refused store admin callers');
});

describe('store', function () {
    test('a staff member is created with their role and a hashed password', function () {
        actingAsStoreUser(createStoreStaff('add_staff'));

        $this->post('/temp/admin/staffs', storeStaffForm())->assertRedirect(route('staffs.index'));

        $created = StoreUser::where('email', 'new-staff@example.com')->firstOrFail();

        expect($created->only(['name', 'phone', 'user_type']))->toBe(['name' => 'New Staff', 'phone' => '0612345678', 'user_type' => 'staff'])
            ->and(Hash::check('a-long-password', $created->password))->toBeTrue()
            ->and($created->hasRole('Clerk', 'store'))->toBeTrue()
            ->and(Staff::where('user_id', $created->id)->value('role_id'))->toBe(Role::findByName('Clerk', 'store')->id);
    });

    test('creating staff validates the input', function () {
        actingAsStoreUser(createStoreStaff('add_staff'));
        $taken = createStoreUser('customer');

        $this->post('/temp/admin/staffs', storeStaffForm(['email' => $taken->email, 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors(['email', 'password']);
        $this->post('/temp/admin/staffs', storeStaffForm(['name' => '', 'mobile' => '', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['name', 'mobile', 'email']);
        $this->post('/temp/admin/staffs', storeStaffForm(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors('password');
        $this->post('/temp/admin/staffs', storeStaffForm(['name' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('name');

        expect(Staff::count())->toBe(0)
            ->and(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('creating staff refuses the Super Admin role, a warehouse role and a role that does not exist', function () {
        actingAsStoreUser(createStoreStaff('add_staff'));

        foreach ([Role::findOrCreate(Role::SUPER_ADMIN, 'store')->id, SpatieRole::findOrCreate('admin', 'web')->id, 999999] as $roleId) {
            $this->post('/temp/admin/staffs', storeStaffForm(['role_id' => $roleId]))->assertSessionHasErrors('role_id');
        }

        expect(Staff::count())->toBe(0)
            ->and(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('a failure part way through creating staff leaves no half-made account', function () {
        actingAsStoreUser(createStoreStaff('add_staff'));
        Event::listen('eloquent.creating: '.Staff::class, fn () => throw new RuntimeException('boom'));

        try {
            $this->post('/temp/admin/staffs', storeStaffForm())->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.Staff::class);
        }

        expect(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('creating staff refuses a caller without the permission, and creates nothing', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->post('/temp/admin/staffs', storeStaffForm()), $caller);

        expect(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse()
            ->and(Staff::count())->toBe(0);
    })->with('refused store admin callers');
});

describe('edit', function () {
    test('the edit-staff form can be rendered with the member and the roles', function () {
        $clerk = Role::findOrCreate('Clerk', 'store');
        actingAsStoreUser(createStoreStaff('edit_staff'));
        $member = createStoreUser('staff', 'sam@example.com');
        $row = createStaffRow($member);

        $this->get("/temp/admin/staffs/{$row->id}/edit")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Staff/Staffs/Edit')
                ->has('staffMember', fn (Assert $staff) => $staff
                    ->where('id', $row->id)
                    ->where('user_id', $member->id)
                    ->where('name', $member->name)
                    ->where('email', 'sam@example.com')
                    ->where('phone', $member->phone)
                    ->where('is_super_admin', false)
                    ->where('role', ['id' => $clerk->id, 'name' => 'Clerk']))
                ->where('roles', [['id' => $clerk->id, 'name' => 'Clerk']])
        );
    });

    test("a Super Admin's account cannot be opened for editing", function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));
        $row = createStaffRow(createStoreAdmin(), Role::SUPER_ADMIN);

        $this->get("/temp/admin/staffs/{$row->id}/edit")->assertForbidden();
    });

    test('editing a staff member that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));

        $this->get('/temp/admin/staffs/999999/edit')->assertNotFound();
    });

    test('the edit-staff form refuses a caller without the permission', function (string $caller) {
        $row = createStaffRow(createStoreUser('staff'));
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get("/temp/admin/staffs/{$row->id}/edit"), $caller);
    })->with('refused store admin callers');
});

describe('update', function () {
    test("updating staff keeps their own email, refuses another account's, and checks the password", function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));
        $member = createStoreUser('staff');
        $row = createStaffRow($member);
        $other = createStoreUser('customer');
        $hash = $member->password;

        $this->put("/temp/admin/staffs/{$row->id}", storeStaffForm(['email' => $other->email, 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('email');
        $this->put("/temp/admin/staffs/{$row->id}", storeStaffForm(['email' => $member->email, 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        expect($member->fresh()->only(['name', 'email', 'password']))->toBe(['name' => $member->name, 'email' => $member->email, 'password' => $hash]);

        $this->put("/temp/admin/staffs/{$row->id}", storeStaffForm(['name' => 'Renamed', 'email' => $member->email, 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect(route('staffs.index'));

        expect($member->fresh()->name)->toBe('Renamed')
            ->and($member->fresh()->password)->toBe($hash)
            ->and($other->fresh()->name)->toBe($other->name);
    });

    test('updating staff moves them to the new role and can reset their password', function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));
        $member = createStoreUser('staff');
        $row = createStaffRow($member);
        $manager = Role::findOrCreate('Manager', 'store');

        $this->put("/temp/admin/staffs/{$row->id}", storeStaffForm([
            'email' => $member->email,
            'role_id' => $manager->id,
            'password' => 'another-long-password',
            'password_confirmation' => 'another-long-password',
        ]))->assertRedirect(route('staffs.index'));

        expect($row->fresh()->role_id)->toBe($manager->id)
            ->and($member->fresh()->roles->pluck('name')->all())->toBe(['Manager'])
            ->and(Hash::check('another-long-password', $member->fresh()->password))->toBeTrue();
    });

    test('a Super Admin role cannot be assigned, nor a Super Admin account edited', function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));
        $superAdmin = createStoreAdmin();
        $superRow = createStaffRow($superAdmin, Role::SUPER_ADMIN);
        $member = createStoreUser('staff');
        $row = createStaffRow($member);

        $this->put("/temp/admin/staffs/{$row->id}", storeStaffForm(['email' => $member->email, 'role_id' => Role::findByName(Role::SUPER_ADMIN, 'store')->id]))
            ->assertSessionHasErrors('role_id');
        $this->put("/temp/admin/staffs/{$superRow->id}", storeStaffForm(['email' => 'taken-over@example.com']))
            ->assertForbidden();

        expect($member->fresh()->roles->pluck('name')->all())->toBe(['Clerk'])
            ->and($superAdmin->fresh()->email)->toBe($superAdmin->email);
    });

    test('updating a staff member that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('edit_staff'));

        $this->put('/temp/admin/staffs/999999', storeStaffForm())->assertNotFound();
    });

    test('updating staff refuses a caller without the permission, and changes nothing', function (string $caller) {
        $member = createStoreUser('staff');
        $row = createStaffRow($member);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->put("/temp/admin/staffs/{$row->id}", storeStaffForm(['email' => 'taken-over@example.com'])), $caller);

        expect($member->fresh()->email)->toBe($member->email)
            ->and($member->fresh()->name)->toBe($member->name);
    })->with('refused store admin callers');
});

describe('destroy', function () {
    test('staff cannot delete themselves, and can delete another member, back on the same page', function () {
        $actor = createStoreStaff('delete_staff');
        $ownRow = createStaffRow($actor);
        $member = createStoreUser('staff');
        $row = createStaffRow($member);
        actingAsStoreUser($actor);

        $this->delete("/temp/admin/staffs/{$ownRow->id}")->assertForbidden();

        expect(StoreUser::find($actor->id))->not->toBeNull()->and(Staff::count())->toBe(2);

        $this->delete("/temp/admin/staffs/{$row->id}?page=2&per_page=10")
            ->assertRedirect(route('staffs.index', ['page' => 2, 'per_page' => 10]));

        expect(StoreUser::find($member->id))->toBeNull()
            ->and(Staff::find($row->id))->toBeNull()
            ->and(Staff::find($ownRow->id))->not->toBeNull();
    });

    test("a Super Admin's account cannot be deleted", function () {
        actingAsStoreUser(createStoreStaff('delete_staff'));
        $superAdmin = createStoreAdmin();
        $row = createStaffRow($superAdmin, Role::SUPER_ADMIN);

        $this->delete("/temp/admin/staffs/{$row->id}")->assertForbidden();

        expect(StoreUser::find($superAdmin->id))->not->toBeNull()
            ->and(Staff::find($row->id))->not->toBeNull();
    });

    test('deleting a staff member that does not exist returns 404', function () {
        actingAsStoreUser(createStoreStaff('delete_staff'));

        $this->delete('/temp/admin/staffs/999999')->assertNotFound();
    });

    test('deleting staff refuses a caller without the permission, and deletes nothing', function (string $caller) {
        $member = createStoreUser('staff');
        $row = createStaffRow($member);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->delete("/temp/admin/staffs/{$row->id}"), $caller);

        expect(StoreUser::find($member->id))->not->toBeNull()
            ->and(Staff::find($row->id))->not->toBeNull();
    })->with('refused store admin callers');

    test('deleting no longer answers the old POST route', function () {
        actingAsStoreUser(createStoreStaff('delete_staff'));
        $row = createStaffRow(createStoreUser('staff'));

        $this->post("/temp/admin/staffs/destroy/{$row->id}")->assertNotFound();

        expect(Staff::find($row->id))->not->toBeNull();
    });
});
