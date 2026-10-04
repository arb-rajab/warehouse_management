<?php

use App\Http\Controllers\ReportController;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * The admin panel's history reports, driver and trip screens and staff
 * screens: the report's seller guard was overridden by a request value, the
 * driver actions took any account by id (so a staff member could reset an
 * admin's password), the trip screens had no permission at all, and the staff
 * screens saved unvalidated input, half-written, for any role and any member.
 */
function gapStaffWith(string ...$permissions): StoreUser
{
    $user = createStoreUser('staff');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'store');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function gapStaffRow(StoreUser $user, string $roleName = 'Clerk'): Staff
{
    $role = Role::findOrCreate($roleName, 'store');
    $user->assignRole($roleName);

    return Staff::forceCreate(['user_id' => $user->id, 'role_id' => $role->id]);
}

function gapStaffForm(array $overrides = []): array
{
    return ['name' => 'New Staff', 'email' => 'new-staff@example.com', 'mobile' => '0612345678', 'password' => 'a-long-password', 'role_id' => Role::findOrCreate('Clerk', 'store')->id, ...$overrides];
}

describe('commission and wallet reports', function () {
    test('a seller who is also sent a seller_id still gets only their own commission rows', function () {
        $seller = createStoreUser('seller');
        $other = createStoreUser('seller');
        actingAsStoreUser($seller);
        Auth::shouldUse('store');

        DB::enableQueryLog();
        try {
            (new ReportController)->commission_history(Request::create('/', 'GET', ['seller_id' => $other->id]));
        } catch (Throwable) {
            // The seller branch's view does not exist; only the query matters.
        }
        $bindings = collect(DB::getQueryLog())->pluck('bindings')->flatten()->all();

        expect($bindings)->toContain($seller->id)->not->toContain($other->id);
    });

    test('a malformed or reversed date range is refused on both reports', function (string $uri, string $range) {
        actingAsStoreUser(createStoreAdmin());

        $this->get("{$uri}?date_range=".urlencode($range))->assertSessionHasErrors('date_range');
    })->with([
        'commission, text' => ['/temp/admin/commission-log', 'yesterday'],
        'commission, reversed' => ['/temp/admin/commission-log', '2026-02-01 / 2026-01-01'],
        'commission, impossible day' => ['/temp/admin/commission-log', '2026-02-31 / 2026-03-01'],
        'wallet, text' => ['/temp/admin/wallet-history', 'yesterday'],
        'wallet, reversed' => ['/temp/admin/wallet-history', '2026-02-01 / 2026-01-01'],
    ]);

    test('a valid date range and account filter are accepted', function () {
        actingAsStoreUser(createStoreAdmin());

        $this->get('/temp/admin/commission-log?date_range='.urlencode('2026-01-01 / 2026-02-01').'&seller_id=1')->assertSessionDoesntHaveErrors();
        $this->get('/temp/admin/commission-log?seller_id=abc')->assertSessionHasErrors('seller_id');
    });

    test('a staff member without the report permission is refused', function () {
        actingAsStoreUser(createStoreUser('staff'));

        $this->get('/temp/admin/commission-log')->assertForbidden();
    });
});

describe('trip, driver and truck permissions', function () {
    test('a staff member needs the permission of each trip, driver and truck action', function (string $verb, string $uri, string $permission) {
        $staff = gapStaffWith();
        actingAsStoreUser($staff);

        expect($this->call($verb, $uri)->getStatusCode())->toBe(403);

        Permission::findOrCreate($permission, 'store');
        $staff->givePermissionTo($permission);

        expect($this->call($verb, $uri)->getStatusCode())->not->toBe(403);
    })->with([
        ['GET', '/temp/admin/trips/index', 'view_all_trips'],
        ['GET', '/temp/admin/trips/create', 'add_trip'],
        ['POST', '/temp/admin/trips/store', 'add_trip'],
        ['POST', '/temp/admin/trips/update', 'edit_trip'],
        ['POST', '/temp/admin/trips/update-trip-status', 'edit_trip'],
        ['POST', '/temp/admin/trips/delete/1', 'delete_trip'],
        ['POST', '/temp/admin/trips/bulk-trips-delete', 'delete_trip'],
        ['GET', '/temp/admin/trips/drivers/index', 'view_all_drivers'],
        ['GET', '/temp/admin/trips/drivers/create', 'add_driver'],
        ['POST', '/temp/admin/trips/drivers/store', 'add_driver'],
        ['POST', '/temp/admin/trips/drivers/update', 'edit_driver'],
        ['POST', '/temp/admin/trips/drivers/delete/1', 'delete_driver'],
        ['GET', '/temp/admin/trips/trucks/index', 'view_all_trucks'],
        ['POST', '/temp/admin/trips/trucks/store', 'add_truck'],
        ['POST', '/temp/admin/trips/trucks/update', 'edit_truck'],
        ['POST', '/temp/admin/trips/trucks/delete/1', 'delete_truck'],
    ]);

    test('a guest and a customer are refused on the driver actions and change nothing', function (string $callerType) {
        $driver = createStoreUser('driver');
        $hash = $driver->password;
        if ($callerType !== 'guest') {
            actingAsStoreUser(createStoreUser($callerType));
        }

        $statuses = [
            $this->get("/temp/admin/trips/drivers/edit/{$driver->id}")->getStatusCode(),
            $this->post('/temp/admin/trips/drivers/update', ['id' => $driver->id, 'name' => 'Hijacked', 'phone' => '1', 'country' => 'NL', 'password' => 'new-password'])->getStatusCode(),
            $this->post("/temp/admin/trips/drivers/delete/{$driver->id}")->getStatusCode(),
        ];

        expect($statuses)->each->toBeIn([302, 401, 404])
            ->and($driver->fresh()->only(['name', 'password']))->toBe(['name' => $driver->name, 'password' => $hash])
            ->and(StoreUser::find($driver->id))->not->toBeNull();
    })->with(['guest', 'customer']);
});

describe('driver accounts', function () {
    test('the driver actions cannot reach an account that is not a driver', function () {
        $victim = createStoreUser('admin');
        $hash = $victim->password;
        actingAsStoreUser(gapStaffWith('edit_driver', 'delete_driver'));

        $this->get("/temp/admin/trips/drivers/edit/{$victim->id}")->assertNotFound();
        $this->post('/temp/admin/trips/drivers/update', ['id' => $victim->id, 'name' => 'Hijacked', 'phone' => '1', 'country' => 'NL', 'password' => 'new-password'])->assertNotFound();
        $this->post("/temp/admin/trips/drivers/delete/{$victim->id}")->assertNotFound();

        expect($victim->fresh()->only(['name', 'password']))->toBe(['name' => $victim->name, 'password' => $hash]);
    });

    test('a driver can be edited and deleted, and only that driver', function () {
        $driver = createStoreUser('driver');
        $noise = createStoreUser('driver');
        actingAsStoreUser(gapStaffWith('edit_driver', 'delete_driver'));

        $this->post('/temp/admin/trips/drivers/update', ['id' => $driver->id, 'name' => 'Renamed', 'phone' => '1', 'country' => 'NL', 'password' => 'new-password'])->assertRedirect();

        expect($driver->fresh()->name)->toBe('Renamed')
            ->and(Hash::check('new-password', $driver->fresh()->password))->toBeTrue()
            ->and($noise->fresh()->name)->toBe($noise->name);

        $this->post("/temp/admin/trips/drivers/delete/{$driver->id}")->assertRedirect();

        expect(StoreUser::find($driver->id))->toBeNull()->and(StoreUser::find($noise->id))->not->toBeNull();
    });

    test('a driver update needs a name, phone, country and a password of six characters', function () {
        $driver = createStoreUser('driver');
        actingAsStoreUser(gapStaffWith('edit_driver'));

        $this->post('/temp/admin/trips/drivers/update', ['id' => $driver->id, 'password' => '123'])->assertSessionHasErrors(['name', 'phone', 'country', 'password']);

        expect($driver->fresh()->name)->toBe($driver->name);
    });

    test('editing a driver that does not exist returns 404', function () {
        actingAsStoreUser(gapStaffWith('edit_driver', 'delete_driver'));

        $this->get('/temp/admin/trips/drivers/edit/999999')->assertNotFound();
        $this->post('/temp/admin/trips/drivers/delete/999999')->assertNotFound();
    });
});

describe('staff accounts', function () {
    test('a guest, a customer and a staff member without the permission are refused on every staff action', function (string $callerType) {
        $target = createStoreUser('staff');
        $row = gapStaffRow($target);
        if ($callerType === 'unpermitted staff') {
            actingAsStoreUser(createStoreUser('staff'));
        } elseif ($callerType !== 'guest') {
            actingAsStoreUser(createStoreUser($callerType));
        }

        $statuses = [
            $this->post('/temp/admin/staffs', gapStaffForm())->getStatusCode(),
            $this->patch("/temp/admin/staffs/{$row->id}", gapStaffForm(['email' => 'taken-over@example.com']))->getStatusCode(),
            $this->post("/temp/admin/staffs/destroy/{$row->id}")->getStatusCode(),
        ];

        expect($statuses)->each->toBeIn([302, 401, 403, 404])
            ->and(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse()
            ->and($target->fresh()->email)->toBe($target->email)
            ->and(Staff::count())->toBe(1);
    })->with(['guest', 'customer', 'unpermitted staff']);

    test('creating staff validates the input, and a Super Admin role cannot be given', function () {
        actingAsStoreUser(gapStaffWith('add_staff'));
        $taken = createStoreUser('customer');
        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, 'store');

        $this->post('/temp/admin/staffs', gapStaffForm(['email' => $taken->email, 'password' => 'short']))->assertSessionHasErrors(['email', 'password']);
        $this->post('/temp/admin/staffs', gapStaffForm(['name' => '', 'mobile' => '']))->assertSessionHasErrors(['name', 'mobile']);
        $this->post('/temp/admin/staffs', gapStaffForm(['role_id' => $superAdmin->id]))->assertNotFound();
        $this->post('/temp/admin/staffs', gapStaffForm(['role_id' => 999999]))->assertNotFound();

        expect(Staff::count())->toBe(0)
            ->and(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('a staff member is created with their role', function () {
        actingAsStoreUser(gapStaffWith('add_staff'));

        $this->post('/temp/admin/staffs', gapStaffForm())->assertRedirect();

        $created = StoreUser::where('email', 'new-staff@example.com')->firstOrFail();

        expect($created->user_type)->toBe('staff')
            ->and($created->hasRole('Clerk', 'store'))->toBeTrue()
            ->and(Staff::where('user_id', $created->id)->count())->toBe(1);
    });

    test('a failure part way through creating staff leaves no half-made account', function () {
        actingAsStoreUser(gapStaffWith('add_staff'));
        Event::listen('eloquent.creating: '.Staff::class, fn () => throw new RuntimeException('boom'));

        try {
            $this->post('/temp/admin/staffs', gapStaffForm())->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.Staff::class);
        }

        expect(StoreUser::where('email', 'new-staff@example.com')->exists())->toBeFalse();
    });

    test('updating staff keeps their own email, refuses another account\'s, and checks the password', function () {
        actingAsStoreUser(gapStaffWith('edit_staff'));
        $member = createStoreUser('staff');
        $row = gapStaffRow($member);
        $other = createStoreUser('customer');
        $hash = $member->password;

        $this->patch("/temp/admin/staffs/{$row->id}", gapStaffForm(['email' => $other->email, 'password' => '']))->assertSessionHasErrors('email');
        $this->patch("/temp/admin/staffs/{$row->id}", gapStaffForm(['email' => $member->email, 'password' => 'short']))->assertSessionHasErrors('password');

        expect($member->fresh()->only(['name', 'email', 'password']))->toBe(['name' => $member->name, 'email' => $member->email, 'password' => $hash]);

        $this->patch("/temp/admin/staffs/{$row->id}", gapStaffForm(['name' => 'Renamed', 'email' => $member->email, 'password' => '']))->assertRedirect();

        expect($member->fresh()->name)->toBe('Renamed')
            ->and($member->fresh()->password)->toBe($hash)
            ->and($other->fresh()->name)->toBe($other->name);
    });

    test('a Super Admin role cannot be assigned or a Super Admin account edited or deleted through the staff screens', function () {
        actingAsStoreUser(gapStaffWith('edit_staff', 'delete_staff'));
        $superAdmin = createStoreAdmin();
        $superRow = gapStaffRow($superAdmin, Role::SUPER_ADMIN);
        $member = createStoreUser('staff');
        $row = gapStaffRow($member);

        $this->patch("/temp/admin/staffs/{$row->id}", gapStaffForm(['role_id' => Role::findOrCreate(Role::SUPER_ADMIN, 'store')->id]))->assertNotFound();
        $this->patch("/temp/admin/staffs/{$superRow->id}", gapStaffForm(['email' => 'taken-over@example.com']))->assertForbidden();
        $this->post("/temp/admin/staffs/destroy/{$superRow->id}")->assertForbidden();

        expect($member->fresh()->hasRole('Clerk', 'store'))->toBeTrue()
            ->and($superAdmin->fresh()->email)->toBe($superAdmin->email)
            ->and(StoreUser::find($superAdmin->id))->not->toBeNull()
            ->and(Staff::count())->toBe(2);
    });

    test('staff cannot delete themselves, and can delete another member', function () {
        $actor = gapStaffWith('delete_staff');
        $ownRow = gapStaffRow($actor);
        $member = createStoreUser('staff');
        $row = gapStaffRow($member);
        actingAsStoreUser($actor);

        $this->post("/temp/admin/staffs/destroy/{$ownRow->id}")->assertForbidden();

        expect(StoreUser::find($actor->id))->not->toBeNull()->and(Staff::count())->toBe(2);

        $this->post("/temp/admin/staffs/destroy/{$row->id}")->assertRedirect();

        expect(StoreUser::find($member->id))->toBeNull()
            ->and(Staff::find($row->id))->toBeNull()
            ->and(Staff::find($ownRow->id))->not->toBeNull();
    });

    test('updating or deleting a staff member that does not exist returns 404', function () {
        actingAsStoreUser(gapStaffWith('edit_staff', 'delete_staff'));

        $this->patch('/temp/admin/staffs/999999', gapStaffForm())->assertNotFound();
        $this->post('/temp/admin/staffs/destroy/999999')->assertNotFound();
    });
});
