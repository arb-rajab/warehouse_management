<?php

use App\Models\Permission;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function storeTruckForm(array $overrides = []): array
{
    return ['name' => 'Cargo truck', 'model' => 'Volvo', 'max_pallets_number' => '32', 'license_plate' => 'AB-123', 'description' => 'Refrigerated', 'photos' => [], ...$overrides];
}

describe('truck list', function () {
    test('the truck list can be rendered', function () {
        actingAsStoreUser(createStoreStaff('view_all_trucks'));
        $this->get(route('trucks.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Trucks/Index'));
    });

    test('it returns the complete narrow truck and image shapes with the latest open trip', function () {
        actingAsStoreUser(createStoreStaff('view_all_trucks'));
        $photo = Upload::factory()->create(['file_original_name' => 'Truck photo', 'file_name' => 'uploads/truck.jpg', 'external_link' => 'https://images.example/truck.jpg', 'type' => 'image']);
        $truck = Truck::create([...storeTruckForm(), 'photos' => (string) $photo->id]);
        $driver = createStoreUser('driver');
        $open = Trip::create(['driver_id' => $driver->id, 'truck_id' => $truck->id, 'status' => 'pending', 'code' => 'OPEN', 'due_date' => '2026-10-10']);
        backdate($open, '2026-10-08');
        Trip::create(['driver_id' => $driver->id, 'truck_id' => $truck->id, 'status' => 'closed', 'code' => 'CLOSED', 'due_date' => '2026-10-10']);
        $this->get(route('trucks.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['per_page' => 20, 'search' => ''])
            ->has('trucks.data', 1)
            ->has('trucks.data.0', fn (Assert $row) => $row->where('id', $truck->id)->where('name', 'Cargo truck')->where('model', 'Volvo')->where('max_pallets_number', '32')->where('description', 'Refrigerated')->where('license_plate', 'AB-123')->where('photos', [$photo->id])->where('open_trip_id', $open->id))
            ->has('photos', 1)->has('photos.0', fn (Assert $image) => $image->where('id', $photo->id)->where('name', 'Truck photo')->where('url', 'https://images.example/truck.jpg')));
    });

    test('it pages and searches names and license plates', function () {
        actingAsStoreUser(createStoreStaff('view_all_trucks'));
        foreach (range(1, 22) as $id) {
            Truck::create(['name' => 'Cargo '.$id, 'license_plate' => 'PLATE-'.$id]);
        }
        $this->get(route('trucks.index'))->assertInertia(fn (Assert $page) => $page->has('trucks.data', 20)->where('trucks.meta.total', 22));
        $this->get(route('trucks.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('trucks.data', 10)->where('filters.per_page', 10));
        foreach (['Cargo 22', 'PLATE-22'] as $search) {
            $this->get(route('trucks.index', ['search' => $search]))->assertInertia(fn (Assert $page) => $page->has('trucks.data', 1)->where('trucks.data.0.name', 'Cargo 22'));
        }
        $this->getJson(route('trucks.index', ['per_page' => 999]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });

    test('refused callers cannot list trucks', function (string $caller) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('trucks.index')), $caller);
    })->with('refused store admin callers');
});

describe('truck forms', function () {
    test('the create screen can be rendered', function () {
        actingAsStoreUser(createStoreStaff('add_truck'));
        $this->get(route('trucks.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Trucks/Create'));
    });

    test('the edit screen can be rendered and preserves selected photos', function () {
        actingAsStoreUser(createStoreStaff('edit_truck'));
        $photo = Upload::factory()->create(['type' => 'image']);
        $truck = Truck::create(['name' => 'Truck', 'photos' => (string) $photo->id]);
        $this->get(route('trucks.edit', $truck))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Trucks/Edit')->where('truck.photos', [$photo->id])->where('truck.open_trip_id', null)->has('photos', 1));
    });

    test('a missing truck edit is 404', function () {
        actingAsStoreUser(createStoreStaff('edit_truck'));
        $this->get(route('trucks.edit', 999999))->assertNotFound();
    });

    test('refused callers cannot create or edit trucks', function (string $caller) {
        $truck = Truck::create(['name' => 'Original']);
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('trucks.create')), $caller);
        assertStoreCallerRefused($this->get(route('trucks.edit', $truck)), $caller);
    })->with('refused store admin callers');
});

describe('truck writes', function () {
    test('staff can create a truck with existing images and cannot set its availability', function () {
        actingAsStoreUser(createStoreStaff('add_truck'));
        $photo = Upload::factory()->create(['type' => 'image', 'user_id' => createStoreUser('customer')->id]);
        $this->post(route('trucks.store'), storeTruckForm(['photos' => [$photo->id], 'is_available' => false]))->assertRedirect(route('trucks.index'));
        $truck = Truck::firstOrFail();
        expect($truck->name)->toBe('Cargo truck')->and($truck->photos)->toBe((string) $photo->id)->and($truck->is_available)->toBeTrue();
    });

    test('staff can update a truck without changing trip assignments or availability', function () {
        actingAsStoreUser(createStoreStaff('edit_truck'));
        $truck = Truck::create(['name' => 'Original', 'license_plate' => 'AB-123', 'is_available' => false]);
        $other = Truck::create(['name' => 'Other']);
        $photo = Upload::factory()->create(['type' => 'image']);
        $trip = Trip::create(['driver_id' => createStoreUser('driver')->id, 'truck_id' => $truck->id, 'due_date' => '2026-10-10', 'status' => 'pending', 'code' => 'ASSIGNED']);
        $this->put(route('trucks.update', $truck), storeTruckForm(['id' => $other->id, 'is_available' => true, 'photos' => [$photo->id]]))->assertRedirect(route('trucks.index'));
        expect($truck->fresh()->name)->toBe('Cargo truck')->and($truck->fresh()->photos)->toBe((string) $photo->id)->and($truck->fresh()->is_available)->toBeFalse()->and($other->fresh()->name)->toBe('Other')->and($trip->fresh()->truck_id)->toBe($truck->id);
        $this->put(route('trucks.update', $truck), storeTruckForm())->assertRedirect(route('trucks.index'));
        expect($truck->fresh()->photos)->toBe('');
    });

    test('truck creation validates fields and image references', function (array $invalid, string $field) {
        actingAsStoreUser(createStoreStaff('add_truck'));
        $this->postJson(route('trucks.store'), storeTruckForm($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(Truck::count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'long name' => [['name' => str_repeat('x', 51)], 'name'],
        'long model' => [['model' => str_repeat('x', 51)], 'model'],
        'long capacity' => [['max_pallets_number' => str_repeat('x', 51)], 'max_pallets_number'],
        'long description' => [['description' => str_repeat('x', 256)], 'description'],
        'long plate' => [['license_plate' => str_repeat('x', 51)], 'license_plate'],
        'unknown image' => [['photos' => [999999]], 'photos.0'],
        'malformed list' => [['photos' => '1,2'], 'photos'],
        'malformed image' => [['photos' => [['nested']]], 'photos.0'],
    ]);

    test('duplicate plates, nonimage files and repeated photos are refused', function () {
        actingAsStoreUser(createStoreStaff('add_truck', 'edit_truck'));
        $truck = Truck::create(['name' => 'Original', 'license_plate' => 'AB-123']);
        $file = Upload::factory()->create(['type' => 'document']);
        $this->postJson(route('trucks.store'), storeTruckForm())->assertUnprocessable()->assertJsonValidationErrors('license_plate');
        $this->putJson(route('trucks.update', $truck), storeTruckForm(['name' => '', 'photos' => [$file->id]]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'photos.0']);
        $photo = Upload::factory()->create(['type' => 'image']);
        $this->putJson(route('trucks.update', $truck), storeTruckForm(['photos' => [$photo->id, $photo->id]]))->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        expect($truck->fresh()->name)->toBe('Original')->and($truck->fresh()->photos)->toBeNull()->and(Truck::count())->toBe(1);
    });

    test('a missing truck update is 404', function () {
        actingAsStoreUser(createStoreStaff('edit_truck'));
        $this->put(route('trucks.update', 999999), storeTruckForm())->assertNotFound();
        expect(Truck::count())->toBe(0);
    });

    test('refused callers cannot write trucks', function (string $caller) {
        $truck = Truck::create(['name' => 'Original']);
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->post(route('trucks.store'), storeTruckForm()), $caller);
        expect(Truck::count())->toBe(1);
        assertStoreCallerRefused($this->put(route('trucks.update', $truck), storeTruckForm()), $caller);
        expect($truck->fresh()->name)->toBe('Original')->and($truck->fresh()->photos)->toBeNull();
    })->with('refused store admin callers');
});

describe('truck image search and QR', function () {
    test('image search is narrow, paginated and ignores nonimages and unsafe URLs', function () {
        actingAsStoreUser(createStoreStaff('edit_truck'));
        $images = Upload::factory()->count(22)->create(['file_original_name' => 'Cargo photo', 'type' => 'image', 'external_link' => 'javascript:alert(1)']);
        Upload::factory()->create(['file_original_name' => 'Cargo document', 'type' => 'document']);
        $response = $this->getJson(route('trucks.photos', ['search' => 'Cargo']));
        $response->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('has_more', true);
        expect($response->json('data.0'))->toEqual(['id' => $images->last()->id, 'name' => 'Cargo photo', 'url' => null]);
        $this->getJson(route('trucks.photos', ['search' => 'Cargo', 'page' => 2]))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('has_more', false);
        $this->getJson(route('trucks.photos', ['page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson(route('trucks.photos', ['search' => str_repeat('x', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
    });

    test('the truck QR is a server-rendered PDF and a missing plate has no QR', function () {
        actingAsStoreUser(createStoreStaff('view_all_trucks'));
        $truck = Truck::create(['name' => 'Truck', 'license_plate' => 'AB-123']);
        $response = $this->get(route('trucks.qr', $truck))->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
        $this->get(route('trucks.qr', Truck::create(['name' => 'No plate'])))->assertNotFound();
        $this->get(route('trucks.qr', 999999))->assertNotFound();
    });

    test('refused callers cannot search truck images or print QR codes', function (string $caller) {
        $truck = Truck::create(['name' => 'Truck', 'license_plate' => 'AB-123']);
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route('trucks.photos')), $caller);
        assertStoreCallerRefused($this->get(route('trucks.qr', $truck)), $caller);
    })->with('refused store admin callers');
});

test('the unimplemented truck delete routes are retired', function () {
    expect(Route::has('trucks.destroy'))->toBeFalse()
        ->and(Route::has('trucks.bulk-truck-delete'))->toBeFalse();
});

test('an accountant preserves existing attachments but cannot add another owner image', function () {
    $accountant = createStoreUser('accountant');
    foreach (['edit_truck', 'add_truck'] as $permission) {
        Permission::findOrCreate($permission, 'store');
        $accountant->givePermissionTo($permission);
    }
    actingAsStoreUser($accountant);
    $existing = Upload::factory()->create(['type' => 'image', 'user_id' => createStoreUser('staff')->id]);
    $foreign = Upload::factory()->create(['type' => 'image', 'user_id' => createStoreUser('customer')->id]);
    $own = Upload::factory()->create(['type' => 'image', 'user_id' => $accountant->id]);
    $truck = Truck::create(['name' => 'Original', 'photos' => (string) $existing->id]);
    $this->get(route('trucks.edit', $truck))->assertOk()->assertInertia(fn (Assert $page) => $page->where('photos.0.id', $existing->id));
    $this->getJson(route('trucks.photos'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    $this->put(route('trucks.update', $truck), storeTruckForm(['photos' => [$existing->id, $own->id]]))->assertRedirect(route('trucks.index'));
    expect($truck->fresh()->photos)->toBe($existing->id.','.$own->id);
    $this->putJson(route('trucks.update', $truck), storeTruckForm(['photos' => [$foreign->id]]))->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    expect($truck->fresh()->photos)->toBe($existing->id.','.$own->id);
    $this->postJson(route('trucks.store'), storeTruckForm(['license_plate' => 'NEW', 'photos' => [$foreign->id]]))->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    expect(Truck::count())->toBe(1);
});

test('photos must fit the existing CSV database column', function () {
    actingAsStoreUser(createStoreStaff('add_truck'));
    $photos = Upload::factory()->count(100)->create(['type' => 'image'])->modelKeys();
    $this->postJson(route('trucks.store'), storeTruckForm(['photos' => $photos]))->assertUnprocessable()->assertJsonValidationErrors('photos');
    expect(Truck::count())->toBe(0);
});
