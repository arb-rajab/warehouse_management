<?php

use App\Models\Country;
use App\Models\StoreUser;
use App\Models\Trip;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function storeDriverForm(array $overrides = []): array
{
    return [
        'name' => 'New Driver', 'email' => 'new-driver@example.com',
        'phone' => '0612345678', 'country' => 'Netherlands',
        'password' => 'new-password', 'password_confirmation' => 'new-password',
        ...$overrides,
    ];
}

describe('driver index', function () {
    test('the driver list can be rendered', function () {
        actingAsStoreUser(createStoreStaff('view_all_drivers'));
        $this->get(route('drivers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Drivers/Index'));
    });

    test('the driver list returns only driver contacts and searches names and emails', function () {
        actingAsStoreUser(createStoreStaff('view_all_drivers'));
        $driver = createStoreUser('driver', 'sam@example.com');
        $driver->forceFill(['name' => 'Sam Driver', 'phone' => '0612345678', 'country' => 'Netherlands'])->save();
        createStoreUser('customer', 'sam-customer@example.com');
        createStoreUser('driver', 'other@example.com');
        $this->get(route('drivers.index', ['search' => 'sam']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['per_page' => 20, 'search' => 'sam'])
            ->has('drivers.data', 1)
            ->has('drivers.data.0', fn (Assert $row) => $row->where('id', $driver->id)->where('name', 'Sam Driver')->where('email', 'sam@example.com')->where('phone', '0612345678')->where('country', 'Netherlands')));
        $this->get(route('drivers.index', ['search' => 'Sam Driver']))->assertInertia(fn (Assert $page) => $page->has('drivers.data', 1));
    });

    test('drivers paginate with an allowed page size and retain search on page links', function () {
        actingAsStoreUser(createStoreStaff('view_all_drivers'));
        for ($i = 0; $i < 21; $i++) {
            createStoreUser('driver');
        }
        $this->get(route('drivers.index'))->assertInertia(fn (Assert $page) => $page->has('drivers.data', 20)->where('drivers.meta.total', 21));
        $this->get(route('drivers.index', ['per_page' => 10, 'search' => 'Driver']))->assertInertia(fn (Assert $page) => $page->has('drivers.data', 10)->where('drivers.meta.total', 21)->where('filters', ['per_page' => 10, 'search' => 'Driver']));
        $this->get(route('drivers.index', ['per_page' => 999]))->assertSessionHasErrors('per_page');
        $this->get(route('drivers.index', ['search' => ['bad']]))->assertSessionHasErrors('search');
    });

    test('the driver list refuses callers without permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('drivers.index')), $caller);
    })->with('refused store admin callers');
});

describe('driver create and store', function () {
    test('the driver create screen can be rendered with only active countries in priority order', function () {
        actingAsStoreUser(createStoreStaff('add_driver'));
        $country = Country::forceCreate(['name' => 'Netherlands', 'status' => 1, 'priority' => 1]);
        Country::forceCreate(['name' => 'Inactive', 'status' => 0, 'priority' => 0]);
        $this->get(route('drivers.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Drivers/Create')->has('countries', 1)->has('countries.0', fn (Assert $row) => $row->where('id', $country->id)->where('name', 'Netherlands')));
    });

    test('creating a driver fixes the account type and verifies the email', function () {
        actingAsStoreUser(createStoreStaff('add_driver'));
        $this->post(route('drivers.store'), storeDriverForm(['user_type' => 'admin', 'rep_id' => 99]))->assertRedirect(route('drivers.index'));
        $driver = StoreUser::where('email', 'new-driver@example.com')->firstOrFail();
        expect($driver->only(['name', 'phone', 'country', 'user_type', 'registration_completed']))->toEqual(['name' => 'New Driver', 'phone' => '0612345678', 'country' => 'Netherlands', 'user_type' => 'driver', 'registration_completed' => 1])
            ->and(Hash::check('new-password', $driver->password))->toBeTrue()->and($driver->email_verified_at)->not->toBeNull()->and($driver->rep_id)->toBeNull();
    });

    test('creating validates required fields, confirmation, uniqueness and database widths', function () {
        actingAsStoreUser(createStoreStaff('add_driver'));
        $before = StoreUser::count();
        $this->post(route('drivers.store'), [])->assertSessionHasErrors(['name', 'email', 'phone', 'country', 'password']);
        $this->post(route('drivers.store'), storeDriverForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        createStoreUser('customer', 'taken@example.com');
        $this->post(route('drivers.store'), storeDriverForm(['email' => 'taken@example.com', 'name' => str_repeat('x', 192), 'phone' => str_repeat('1', 21), 'country' => str_repeat('x', 31), 'password' => '123', 'password_confirmation' => '123']))->assertSessionHasErrors(['name', 'email', 'phone', 'country', 'password']);
        expect(StoreUser::count())->toBe($before + 1);
    });

    test('the driver create screen refuses callers without permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('drivers.create')), $caller);
    })->with('refused store admin callers');

    test('creating a driver refuses callers and creates no accounts', function (string $caller) {
        actingAsRefusedStoreCaller($caller);
        $before = StoreUser::count();
        assertStoreCallerRefused($this->post(route('drivers.store'), storeDriverForm()), $caller);
        expect(StoreUser::count())->toBe($before);
    })->with('refused store admin callers');
});

describe('driver edit and update', function () {
    test('the driver edit screen can be rendered with the complete contact payload', function () {
        actingAsStoreUser(createStoreStaff('edit_driver'));
        $driver = createStoreUser('driver', 'sam@example.com');
        $driver->forceFill(['name' => 'Sam Driver', 'phone' => '0612345678', 'country' => 'Netherlands'])->save();
        $this->get(route('drivers.edit', $driver))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Drivers/Edit')->has('countries', 0)->has('driver', fn (Assert $row) => $row->where('id', $driver->id)->where('name', 'Sam Driver')->where('email', 'sam@example.com')->where('phone', '0612345678')->where('country', 'Netherlands')));
    });

    test('updating changes only the driver contacts and keeps their email type and unsubmitted password', function () {
        actingAsStoreUser(createStoreStaff('edit_driver'));
        $driver = createStoreUser('driver');
        $noise = createStoreUser('driver');
        $before = $driver->only(['email', 'user_type', 'password']);
        $this->put(route('drivers.update', $driver), storeDriverForm(['password' => '', 'email' => 'hijacked@example.com', 'user_type' => 'admin', 'id' => $noise->id]))->assertRedirect(route('drivers.index'));
        expect($driver->fresh()->name)->toBe('New Driver')->and($driver->fresh()->only(['email', 'user_type', 'password']))->toEqual($before)->and($noise->fresh()->name)->toBe($noise->name);
        $this->put(route('drivers.update', $driver), storeDriverForm())->assertRedirect(route('drivers.index'));
        expect(Hash::check('new-password', $driver->fresh()->password))->toBeTrue();
    });

    test('updating a driver validates contacts and an optional confirmed six character password', function () {
        actingAsStoreUser(createStoreStaff('edit_driver'));
        $driver = createStoreUser('driver');
        $this->put(route('drivers.update', $driver), ['password' => '123'])->assertSessionHasErrors(['name', 'phone', 'country', 'password']);
        $this->put(route('drivers.update', $driver), storeDriverForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        expect($driver->fresh()->name)->toBe($driver->name);
    });

    test('the driver edit screen refuses callers without permission', function (string $caller) {
        $driver = createStoreUser('driver');
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('drivers.edit', $driver)), $caller);
    })->with('refused store admin callers');

    test('updating refuses callers and leaves the account unchanged', function (string $caller) {
        $driver = createStoreUser('driver');
        $before = $driver->getAttributes();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->put(route('drivers.update', $driver), storeDriverForm()), $caller);
        expect($driver->fresh()->getAttributes())->toEqual($before);
    })->with('refused store admin callers');
});

describe('driver destroy', function () {
    test('deleting a driver clears only that driver trips without deleting trips', function () {
        actingAsStoreUser(createStoreStaff('delete_driver'));
        $driver = createStoreUser('driver');
        $noise = createStoreUser('driver');
        $trip = Trip::create(['code' => 'TRIP1', 'driver_id' => $driver->id, 'status' => 'started', 'active' => true]);
        $other = Trip::create(['code' => 'TRIP2', 'driver_id' => $noise->id]);
        $this->delete(route('drivers.destroy', $driver).'?page=2&per_page=10&search=Sam')->assertRedirect(route('drivers.index', ['page' => 2, 'per_page' => 10, 'search' => 'Sam']));
        expect(StoreUser::find($driver->id))->toBeNull()->and($noise->fresh())->not->toBeNull()->and($trip->fresh()->driver_id)->toBeNull()->and($trip->fresh()->status)->toBe('started')->and($trip->fresh()->active)->toBeTrue()->and($other->fresh()->driver_id)->toBe($noise->id)->and(Trip::count())->toBe(2);
    });

    test('deleting refuses callers and leaves the driver and trips unchanged', function (string $caller) {
        $driver = createStoreUser('driver');
        $trip = Trip::create(['code' => 'TRIP1', 'driver_id' => $driver->id]);
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->delete(route('drivers.destroy', $driver)), $caller);
        expect($driver->fresh())->not->toBeNull()->and($trip->fresh()->driver_id)->toBe($driver->id);
    })->with('refused store admin callers');
});

test('driver binding cannot reach foreign account types even for Super Admin', function (string $userType) {
    $victim = createStoreUser($userType);
    $before = $victim->getAttributes();
    actingAsStoreUser(createStoreAdmin());
    $this->get(route('drivers.edit', $victim))->assertNotFound();
    $this->put(route('drivers.update', $victim), storeDriverForm())->assertNotFound();
    $this->delete(route('drivers.destroy', $victim))->assertNotFound();
    expect($victim->fresh()->getAttributes())->toEqual($before);
})->with(['customer', 'admin', 'staff', 'seller']);

test('missing driver bindings return 404 for edit update and delete', function () {
    actingAsStoreUser(createStoreAdmin());
    $this->get(route('drivers.edit', 999999))->assertNotFound();
    $this->put(route('drivers.update', 999999), storeDriverForm())->assertNotFound();
    $this->delete(route('drivers.destroy', 999999))->assertNotFound();
});
