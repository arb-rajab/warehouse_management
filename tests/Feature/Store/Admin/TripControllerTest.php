<?php

use App\Http\Controllers\Trip\TripController;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->routingResponse = Http::response([], 503);
    Http::fake(fn () => $this->routingResponse);
    $this->mock(UserService::class)->shouldReceive('getUserLongLat')->andReturn(['latitude' => '52.1', 'longitude' => '4.2', 'postal_code' => '1234']);
    $this->mock(DriverService::class)->shouldReceive('notifyDriver');
    $this->departure = createStoreUser()->forceFill(['id' => 9]);
    $this->departure->save();
});

function createTripTestRecord(array $attributes = []): Trip
{
    $truck = Truck::create(['name' => 'Delivery truck', 'is_available' => false]);

    return Trip::create(['code' => 'Trip-'.random_int(1, 99999999), 'truck_id' => $truck->id, 'due_date' => '2026-10-10', 'status' => 'pending', 'active' => true, ...$attributes])->fresh();
}

function createTripTestCheckpoint(Trip $trip, array $attributes = []): Checkpoint
{
    return $trip->checkpoints()->create([
        'relationable_type' => StoreUser::class,
        'relationable_id' => 9,
        'type' => 'other',
        'status' => 'pending',
        'sort_order_in_trip' => 1,
        'longitudes' => '4.2',
        'latitudes' => '52.1',
        ...$attributes,
    ])->fresh();
}

function tripTestPayload(Truck $truck, ?Checkpoint $checkpoint = null): array
{
    return [
        'truck_id' => $truck->id,
        'due_date' => '2026-10-10',
        'notes' => 'Trip note',
        'checkpoints_array' => [[
            'id' => $checkpoint?->id ?? 0,
            'checkpoint_type' => $checkpoint?->type ?? 'other',
            'checkpoint_related_id' => $checkpoint?->relationable_id ?? 9,
            'checkpoint_notes' => 'Checkpoint note',
        ]],
    ];
}

test('a trip can be created with a departure checkpoint and driver using the actual form payload', function () {
    actingAsStoreUser(createStoreStaff('add_trip'));
    $driver = createStoreUser('driver');
    $truck = Truck::create(['name' => 'Available truck']);

    $this->post(route('trips.store'), [...tripTestPayload($truck), 'driver_id' => $driver->id])->assertRedirect();

    $trip = Trip::firstOrFail();
    expect($trip->driver_id)->toBe($driver->id)
        ->and($trip->active)->toBeTrue()
        ->and($trip->checkpoints()->count())->toBe(2)
        ->and($trip->checkpoints()->pluck('relationable_id')->all())->toBe([9, 9])
        ->and($trip->checkpoints()->pluck('id')->all())->not->toContain(0)
        ->and($truck->fresh()->is_available)->toBeFalse();
});

test('trip creation refuses callers and changes no rows', function (string $caller) {
    $truck = Truck::create(['name' => 'Available truck']);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('trips.store'), tripTestPayload($truck)), $caller);

    expect(Trip::count())->toBe(0)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue();
})->with('refused store admin callers');

test('trip creation validates nested checkpoints and driver type before reserving a truck', function (array $replacement, array $errors) {
    actingAsStoreUser(createStoreStaff('add_trip'));
    $truck = Truck::create(['name' => 'Available truck']);
    $payload = [...tripTestPayload($truck), ...$replacement];
    if (isset($replacement['driver_id'])) {
        $payload['driver_id'] = createStoreUser('customer')->id;
    }

    $this->post(route('trips.store'), $payload)->assertSessionHasErrors($errors);

    expect(Trip::count())->toBe(0)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue();
})->with([
    'missing rows' => [['checkpoints_array' => []], ['checkpoints_array']],
    'invalid row' => [['checkpoints_array' => ['bad']], ['checkpoints_array.0']],
    'unknown type' => [['checkpoints_array' => [['checkpoint_type' => 'collection', 'checkpoint_related_id' => 9]]], ['checkpoints_array.0.checkpoint_type']],
    'missing reference' => [['checkpoints_array' => [['checkpoint_type' => 'delivery', 'checkpoint_related_id' => 999999]]], ['checkpoints_array.0.checkpoint_related_id']],
    'customer as driver' => [['driver_id' => true], ['driver_id']],
]);

test('missing configured departure is a validation failure with no writes', function () {
    $this->departure->delete();
    actingAsStoreUser(createStoreStaff('add_trip'));
    $customer = createStoreUser();
    $truck = Truck::create(['name' => 'Available truck']);
    $payload = tripTestPayload($truck);
    $payload['checkpoints_array'][0]['checkpoint_related_id'] = $customer->id;

    $this->post(route('trips.store'), $payload)->assertSessionHasErrors('departure');

    expect(Trip::count())->toBe(0)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue();
});

test('an open trip prevents a second assignment even when the truck availability flag is stale', function () {
    $existing = createTripTestRecord();
    $existing->truck->update(['is_available' => true]);
    actingAsStoreUser(createStoreStaff('add_trip'));

    $this->post(route('trips.store'), tripTestPayload($existing->truck))->assertSessionHasErrors('truck_id');

    expect(Trip::count())->toBe(1)->and(Checkpoint::count())->toBe(0)->and($existing->fresh()->truck_id)->toBe($existing->truck_id);
});

test('editing and updating a missing trip return 404 and the old update URI is gone', function () {
    $trip = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->get(route('trips.edit', 999999))->assertNotFound();
    $this->put(route('trips.update', 999999), tripTestPayload($trip->truck))->assertNotFound();
    $this->post('/temp/admin/trips/update', ['id' => $trip->id, ...tripTestPayload($trip->truck)])->assertMethodNotAllowed();
    $this->get('/temp/admin/trips/edit/'.$trip->id)->assertNotFound();

    expect($trip->fresh()->notes)->toBeNull()->and(Checkpoint::count())->toBe(0);
});

test('trip updates preserve checkpoint identity coordinates status arrival and attachments', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip, ['status' => 'delivered', 'arrived_at' => '2026-10-09 09:00:00', 'cmr_file' => '17', 'photos' => '18,19', 'address' => ['city' => 'Rotterdam']]);
    $noise = createTripTestCheckpoint(createTripTestRecord());
    $newTruck = Truck::create(['name' => 'Replacement truck']);
    $before = $checkpoint->only(['trip_id', 'relationable_id', 'relationable_type', 'type', 'longitudes', 'latitudes', 'status', 'arrived_at', 'cmr_file', 'photos', 'address']);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), tripTestPayload($newTruck, $checkpoint))->assertRedirect(route('trips.show', $trip->id));

    expect($checkpoint->fresh()->only(array_keys($before)))->toEqual($before)
        ->and($checkpoint->fresh()->notes)->toBe('Checkpoint note')
        ->and($noise->fresh()->notes)->toBeNull()
        ->and($trip->fresh()->truck_id)->toBe($newTruck->id)
        ->and($trip->truck->fresh()->is_available)->toBeTrue()
        ->and($newTruck->fresh()->is_available)->toBeFalse();
});

test('trip updates refuse callers without changing trip checkpoint or truck', function (string $caller) {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->put(route('trips.update', $trip), tripTestPayload($trip->truck, $checkpoint)), $caller);

    expect($trip->fresh()->notes)->toBeNull()->and($checkpoint->fresh()->notes)->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
})->with('refused store admin callers');

test('foreign checkpoint ids cannot be copied into or removed from another trip', function () {
    $trip = createTripTestRecord();
    $own = createTripTestCheckpoint($trip);
    $foreign = createTripTestCheckpoint(createTripTestRecord());
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), tripTestPayload($trip->truck, $foreign))->assertSessionHasErrors('checkpoints_array.0.id');

    expect($own->fresh())->not->toBeNull()->and($foreign->fresh()->trip_id)->toBe($foreign->trip_id)->and($trip->fresh()->notes)->toBeNull();
});

test('bound checkpoints cannot be reassigned to a different customer', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip);
    $payload = tripTestPayload($trip->truck, $checkpoint);
    $payload['checkpoints_array'][0]['checkpoint_related_id'] = createStoreUser()->id;
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), $payload)->assertSessionHasErrors('checkpoints_array.0.checkpoint_related_id');

    expect($checkpoint->fresh()->relationable_id)->toBe(9)->and($trip->fresh()->notes)->toBeNull();
});

test('trip updates cannot take a truck from another open trip', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip);
    $other = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), tripTestPayload($other->truck, $checkpoint))->assertSessionHasErrors('truck_id');

    expect($trip->fresh()->truck_id)->toBe($trip->truck_id)->and($other->fresh()->truck_id)->toBe($other->truck_id)->and($checkpoint->fresh()->notes)->toBeNull();
});

test('every existing trip transition keeps dates active state orders and truck availability consistent', function (string $from, string $to) {
    $trip = createTripTestRecord(['status' => $from, 'active' => $from !== 'closed', 'started_at' => $from === 'started' ? '2026-10-09 09:00:00' : null, 'closed_at' => $from === 'closed' ? '2026-10-09 12:00:00' : null]);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => $to])->assertRedirect(route('trips.index'));

    expect($trip->fresh()->status)->toBe($to)
        ->and($trip->fresh()->active)->toBe($to !== 'closed')
        ->and($trip->truck->fresh()->is_available)->toBe($to === 'closed')
        ->and($order->fresh()->delivery_status)->toBe($to === 'closed' ? 'delivered' : 'on_delivery');
    if ($to === 'closed') {
        expect($trip->fresh()->closed_at)->not->toBeNull();
    } else {
        expect($trip->fresh()->closed_at)->toBeNull();
    }
    if ($to === 'started') {
        expect($trip->fresh()->started_at)->not->toBeNull();
    }
})->with([
    ['pending', 'pending'], ['pending', 'started'], ['pending', 'closed'],
    ['started', 'pending'], ['started', 'started'], ['started', 'closed'],
    ['closed', 'pending'], ['closed', 'started'], ['closed', 'closed'],
]);

test('repeated transitions retain the first start close and arrival timestamps', function () {
    $trip = createTripTestRecord(['status' => 'started', 'started_at' => '2026-10-09 09:00:00']);
    $checkpoint = createTripTestCheckpoint($trip, ['status' => 'delivered', 'arrived_at' => '2026-10-09 10:00:00']);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'started'])->assertRedirect();
    $this->post(route('checkpoints.update-status'), ['id' => $checkpoint->id, 'status' => 'delivered'])->assertOk();
    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'closed'])->assertRedirect();
    $closedAt = $trip->fresh()->closed_at;
    $this->travel(1)->hours();
    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'closed'])->assertRedirect();

    expect($trip->fresh()->started_at->toDateTimeString())->toBe('2026-10-09 09:00:00')
        ->and($checkpoint->fresh()->arrived_at->toDateTimeString())->toBe('2026-10-09 10:00:00')
        ->and($trip->fresh()->closed_at)->toEqual($closedAt);
});

test('reopening a closed trip refuses to take a truck now owned by another open trip', function () {
    $trip = createTripTestRecord(['status' => 'closed', 'active' => false, 'closed_at' => '2026-10-09 12:00:00']);
    $other = createTripTestRecord(['truck_id' => $trip->truck_id]);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'started'])->assertSessionHasErrors('truck_id');

    expect($trip->fresh()->status)->toBe('closed')->and($trip->fresh()->active)->toBeFalse()->and($other->fresh()->status)->toBe('pending');
});

test('trip status transitions refuse callers and leave timestamps status orders and truck unchanged', function (string $caller) {
    $trip = createTripTestRecord();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'closed']), $caller);

    expect($trip->fresh()->status)->toBe('pending')->and($trip->fresh()->closed_at)->toBeNull()->and($trip->fresh()->active)->toBeTrue()->and($trip->truck->fresh()->is_available)->toBeFalse()->and($order->fresh()->delivery_status)->toBe('on_delivery');
})->with('refused store admin callers');

test('trip status validation rejects invalid enums, a missing or unknown list and unknown trips', function () {
    $trip = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), ['status' => 'destroyed'])->assertSessionHasErrors('status');
    $this->put(route('trips.update-status', [$trip, 'list' => 'all']), [])->assertSessionHasErrors('status');
    $this->put(route('trips.update-status', $trip), ['status' => 'closed'])->assertSessionHasErrors('list');
    $this->put(route('trips.update-status', [$trip, 'list' => 'seller']), ['status' => 'closed'])->assertSessionHasErrors('list');
    $this->put(route('trips.update-status', ['trip' => 999999, 'list' => 'all']), ['status' => 'closed'])->assertNotFound();
    $this->post('/temp/admin/trips/update-trip-status', ['id' => $trip->id, 'status' => 'closed'])->assertMethodNotAllowed();

    expect($trip->fresh()->status)->toBe('pending')->and($trip->fresh()->closed_at)->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
});

test('a status change returns to the list it was made from with that list\'s filters', function () {
    $trip = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update-status', [$trip, 'list' => 'today', 'page' => 2, 'search' => 'Trip', 'status' => ['pending']]), ['status' => 'started'])
        ->assertRedirect(route('trips.today-trips', ['page' => 2, 'search' => 'Trip', 'status' => ['pending']]));

    expect($trip->fresh()->status)->toBe('started');
});

test('checkpoint statuses set arrival only when delivered', function (string $status) {
    $checkpoint = createTripTestCheckpoint(createTripTestRecord());
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('checkpoints.update-status'), ['id' => $checkpoint->id, 'status' => $status])->assertOk();

    expect($checkpoint->fresh()->status)->toBe($status);
    if ($status === 'delivered') {
        expect($checkpoint->fresh()->arrived_at)->not->toBeNull();
    } else {
        expect($checkpoint->fresh()->arrived_at)->toBeNull();
    }
})->with(['pending', 'has_issue', 'delivered']);

test('checkpoint status transitions refuse callers and preserve checkpoint identity and attachments', function (string $caller) {
    $checkpoint = createTripTestCheckpoint(createTripTestRecord(), ['cmr_file' => '17', 'photos' => '18']);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('checkpoints.update-status'), ['id' => $checkpoint->id, 'status' => 'delivered']), $caller);

    expect($checkpoint->fresh()->status)->toBe('pending')->and($checkpoint->fresh()->arrived_at)->toBeNull()->and($checkpoint->fresh()->cmr_file)->toBe('17')->and($checkpoint->fresh()->photos)->toBe('18');
})->with('refused store admin callers');

test('checkpoint status validation rejects invalid enums and missing checkpoints without writes', function () {
    $checkpoint = createTripTestCheckpoint(createTripTestRecord());
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('checkpoints.update-status'), ['id' => $checkpoint->id, 'status' => 'closed'])->assertSessionHasErrors('status');
    $this->post(route('checkpoints.update-status'), ['id' => 999999, 'status' => 'delivered'])->assertNotFound();

    expect($checkpoint->fresh()->status)->toBe('pending')->and($checkpoint->fresh()->arrived_at)->toBeNull();
});

test('map creation and appending preserve the original truck driver and checkpoints', function () {
    actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));
    $truck = Truck::create(['name' => 'Map truck']);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $second = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $driver = createStoreUser('driver');

    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'driver_id' => $driver->id, 'markers' => [['order_id' => $order->id, 'latitude' => 99]]])->assertOk()->assertExactJson(['success' => true]);
    $trip = Trip::firstOrFail();
    $original = $trip->checkpoints()->get()->map->only(['id', 'trip_id', 'latitudes', 'longitudes', 'status', 'arrived_at', 'cmr_file', 'photos'])->all();
    $this->postJson(route('trips.store-from-map'), ['trip_id' => $trip->id, 'truck_id' => $truck->id, 'markers' => [['order_id' => $second->id]]])->assertOk();

    expect(Trip::count())->toBe(1)->and($trip->fresh()->driver_id)->toBe($driver->id)->and($trip->checkpoints()->count())->toBe(3)
        ->and($trip->checkpoints()->whereIn('id', array_column($original, 'id'))->get()->map->only(['id', 'trip_id', 'latitudes', 'longitudes', 'status', 'arrived_at', 'cmr_file', 'photos'])->all())->toBe($original)
        ->and($trip->checkpoints()->where('relationable_type', Order::class)->pluck('latitudes')->all())->toBe(['52.1', '52.1'])
        ->and($truck->fresh()->is_available)->toBeFalse();
});

test('map creation refuses callers without reserving a truck or creating checkpoints', function (string $caller) {
    $truck = Truck::create(['name' => 'Map truck']);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]]), $caller);

    expect(Trip::count())->toBe(0)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue()->and($order->fresh()->delivery_status)->toBe('on_delivery');
})->with('refused store admin callers');

test('adding map markers to an existing trip additionally requires edit permission', function () {
    $trip = createTripTestRecord();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    actingAsStoreUser(createStoreStaff('add_trip'));

    $this->postJson(route('trips.store-from-map'), ['trip_id' => $trip->id, 'truck_id' => $trip->truck_id, 'markers' => [['order_id' => $order->id]]])->assertForbidden();

    expect(Checkpoint::count())->toBe(0)->and($trip->fresh()->truck_id)->toBe($trip->truck_id);
});

test('map markers require distinct existing delivery orders and matching trip truck', function () {
    $trip = createTripTestRecord();
    $truck = Truck::create(['name' => 'Other truck']);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'pending']);
    actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));

    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => 999999]]])->assertUnprocessable()->assertJsonValidationErrors('markers.0.order_id');
    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]])->assertUnprocessable();
    $order->update(['delivery_status' => 'on_delivery']);
    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id], ['order_id' => $order->id]]])->assertUnprocessable();
    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'trip_id' => $trip->id, 'markers' => [['order_id' => $order->id]]])->assertUnprocessable()->assertJsonValidationErrors('trip_id');

    expect(Trip::count())->toBe(1)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue()->and($trip->fresh()->truck_id)->toBe($trip->truck_id);
});

test('an order on an open trip cannot be assigned again from the map', function () {
    $trip = createTripTestRecord();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $checkpoint = createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
    $truck = Truck::create(['name' => 'Other truck']);
    actingAsStoreUser(createStoreStaff('add_trip'));

    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]])->assertUnprocessable()->assertJsonValidationErrors('markers.0.order_id');

    expect(Trip::count())->toBe(1)->and(Checkpoint::count())->toBe(1)->and($checkpoint->fresh()->trip_id)->toBe($trip->id)->and($truck->fresh()->is_available)->toBeTrue();
});

test('map data handles empty invalid and zero coordinates and retains markers on routing failure', function () {
    $trip = createTripTestRecord();
    $valid = createTripTestCheckpoint($trip, ['latitudes' => '0', 'longitudes' => '0']);
    $invalid = createTripTestCheckpoint($trip, ['latitudes' => '91', 'longitudes' => '4.2', 'sort_order_in_trip' => 2]);
    $controller = app(TripController::class);
    $map = new ReflectionMethod($controller, 'getCheckpointsMapData');

    expect($map->invoke($controller, collect()))->toBe(['long_lat_array' => [], 'coordinates' => []]);
    expect($map->invoke($controller, collect([$valid, $invalid])))->toBe(['long_lat_array' => [['longitude' => '0', 'latitude' => '0', 'label' => '1 | '.$this->departure->name]], 'coordinates' => []]);
    Http::assertNothingSent();
    $second = createTripTestCheckpoint($trip, ['sort_order_in_trip' => 3]);
    $result = $map->invoke($controller, collect([$valid, $second]));

    expect($result['long_lat_array'])->toHaveCount(2)->and($result['coordinates'])->toBe([]);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://router.project-osrm.org/route/'));
});

describe('trip forms', function () {
    test('the create form renders the orders out for delivery and the non-representative store users', function () {
        $customer = createStoreUser();
        $customer->forceFill(['name' => 'Alma Customer'])->save();
        $rep = createStoreUser();
        $rep->forceFill(['name' => 'Rita Rep', 'is_rep' => true])->save();
        $order = createStoreOrder($customer, ['delivery_status' => 'on_delivery', 'code' => '20261014-0002']);
        $repOrder = createStoreOrder($customer, ['delivery_status' => 'on_delivery', 'code' => '20261014-0001', 'by_rep' => 1, 'for_customer' => $rep->id]);
        createStoreOrder($customer, ['delivery_status' => 'delivered', 'code' => '20261014-0003']);
        $staff = createStoreStaff('add_trip');
        actingAsStoreUser($staff);

        $this->get(route('trips.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/Create')
            ->where('orders', [
                ['id' => $order->id, 'code' => '20261014-0002', 'customer' => 'Alma Customer'],
                ['id' => $repOrder->id, 'code' => '20261014-0001', 'customer' => 'Rita Rep'],
            ])
            ->where('customers', StoreUser::query()->where('is_rep', false)->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (StoreUser $user) => ['id' => $user->id, 'name' => (string) $user->name])->all())
            ->where('customers', fn ($customers) => collect($customers)->pluck('id')->contains($customer->id) && ! collect($customers)->pluck('id')->contains($rep->id))
            ->missing('trip'));
    });

    test('the edit form renders the trip and its stored checkpoints in their order', function () {
        $driver = createStoreUser('driver');
        $trip = createTripTestRecord(['driver_id' => $driver->id, 'notes' => 'Trip note', 'status' => 'started']);
        $customer = createStoreUser();
        $customer->forceFill(['name' => 'Alma Customer'])->save();
        $order = createStoreOrder($customer, ['delivery_status' => 'delivered', 'code' => '20261014-0009']);
        $delivery = createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id, 'sort_order_in_trip' => 2, 'notes' => 'Back door']);
        $departure = createTripTestCheckpoint($trip, ['notes' => 'Default starting checkpoint']);
        createTripTestCheckpoint(createTripTestRecord(), ['notes' => 'Noise']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->get(route('trips.edit', $trip))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/Edit')
            ->has('trip', fn (Assert $form) => $form
                ->where('id', $trip->id)->where('code', $trip->code)->where('status', 'started')
                ->where('driver_id', $driver->id)->where('truck_id', $trip->truck_id)
                ->where('due_date', '2026-10-10')->where('notes', 'Trip note')
                ->has('checkpoints', 2)
                ->has('checkpoints.0', fn (Assert $row) => $row
                    ->where('id', $departure->id)->where('type', 'other')->where('related_id', 9)
                    ->where('notes', 'Default starting checkpoint')->where('order_code', null)
                    ->where('customer', $this->departure->name))
                ->has('checkpoints.1', fn (Assert $row) => $row
                    ->where('id', $delivery->id)->where('type', 'delivery')->where('related_id', $order->id)
                    ->where('notes', 'Back door')->where('order_code', '20261014-0009')
                    ->where('customer', 'Alma Customer')))
            ->has('orders')
            ->has('customers'));
    });

    test('a new trip and an update return to the trip\'s Blade checkpoints page', function () {
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));
        $truck = Truck::create(['name' => 'Available truck']);

        $this->post(route('trips.store'), tripTestPayload($truck))->assertRedirect(route('trips.show', Trip::query()->value('id')));
        $trip = Trip::firstOrFail();
        $this->put(route('trips.update', $trip), tripTestPayload($truck))->assertRedirect(route('trips.show', $trip->id));
    });

    test('the trip forms refuse every unauthorized caller', function (string $caller) {
        $trip = createTripTestRecord();
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('trips.create')), $caller);
        assertStoreCallerRefused($this->get(route('trips.edit', $trip)), $caller);
    })->with('refused store admin callers');

    test('each trip form action needs its own permission', function (string $method, string $route, string $permission) {
        $trip = createTripTestRecord();
        $checkpoint = createTripTestCheckpoint($trip);
        $url = in_array($route, ['trips.edit', 'trips.update'], true) ? route($route, $trip) : route($route);
        $payload = $route === 'trips.update' ? tripTestPayload($trip->truck, $checkpoint) : tripTestPayload(Truck::create(['name' => 'Spare truck']));
        actingAsStoreUser(createStoreStaff(...array_values(array_diff(['view_all_trips', 'add_trip', 'edit_trip', 'delete_trip'], [$permission]))));

        $this->call($method, $url, $payload)->assertForbidden();
        expect(Trip::count())->toBe(1)->and($trip->fresh()->notes)->toBeNull()->and($checkpoint->fresh()->notes)->toBeNull();

        actingAsStoreUser(createStoreStaff($permission));
        expect($this->call($method, $url, $payload)->getStatusCode())->toBeIn([200, 302]);
    })->with([
        'create' => ['GET', 'trips.create', 'add_trip'],
        'store' => ['POST', 'trips.store', 'add_trip'],
        'edit' => ['GET', 'trips.edit', 'edit_trip'],
        'update' => ['PUT', 'trips.update', 'edit_trip'],
    ]);

    test('the trip forms validate notes, dates and references as field errors without writing', function (array $replacement, string $error) {
        $trip = createTripTestRecord();
        $checkpoint = createTripTestCheckpoint($trip);
        $truck = Truck::create(['name' => 'Available truck']);
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));

        $this->post(route('trips.store'), [...tripTestPayload($truck), ...$replacement])->assertSessionHasErrors($error);
        $this->put(route('trips.update', $trip), [...tripTestPayload($trip->truck, $checkpoint), ...$replacement])->assertSessionHasErrors($error);

        expect(Trip::count())->toBe(1)->and(Checkpoint::count())->toBe(1)
            ->and($trip->fresh()->notes)->toBeNull()->and($checkpoint->fresh()->notes)->toBeNull()
            ->and($truck->fresh()->is_available)->toBeTrue();
    })->with([
        'notes over 255' => [['notes' => str_repeat('x', 256)], 'notes'],
        'notes not text' => [['notes' => ['bad']], 'notes'],
        'no due date' => [['due_date' => ''], 'due_date'],
        'not a date' => [['due_date' => 'tomorrow-ish'], 'due_date'],
        'no truck' => [['truck_id' => ''], 'truck_id'],
        'unknown truck' => [['truck_id' => 999999], 'truck_id'],
        'unknown driver' => [['driver_id' => 999999], 'driver_id'],
        'unknown order' => [['checkpoints_array' => [['id' => 0, 'checkpoint_type' => 'delivery', 'checkpoint_related_id' => 999999]]], 'checkpoints_array.0.checkpoint_related_id'],
        'unknown customer' => [['checkpoints_array' => [['id' => 0, 'checkpoint_type' => 'other', 'checkpoint_related_id' => 999999]]], 'checkpoints_array.0.checkpoint_related_id'],
        'checkpoint notes over 1000' => [['checkpoints_array' => [['id' => 0, 'checkpoint_type' => 'other', 'checkpoint_related_id' => 9, 'checkpoint_notes' => str_repeat('x', 1001)]]], 'checkpoints_array.0.checkpoint_notes'],
    ]);

    test('the create form refuses a stored checkpoint id', function () {
        $checkpoint = createTripTestCheckpoint(createTripTestRecord());
        $truck = Truck::create(['name' => 'Available truck']);
        actingAsStoreUser(createStoreStaff('add_trip'));

        $this->post(route('trips.store'), tripTestPayload($truck, $checkpoint))->assertSessionHasErrors('checkpoints_array.0.id');

        expect(Trip::count())->toBe(1)->and($truck->fresh()->is_available)->toBeTrue();
    });

    test('moving a trip to another truck frees the old one only when no other open trip holds it', function () {
        $trip = createTripTestRecord();
        $checkpoint = createTripTestCheckpoint($trip);
        $other = createTripTestRecord(['truck_id' => $trip->truck_id]);
        $newTruck = Truck::create(['name' => 'Replacement truck']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.update', $trip), tripTestPayload($newTruck, $checkpoint))->assertRedirect(route('trips.show', $trip->id));

        expect($trip->fresh()->truck_id)->toBe($newTruck->id)->and($newTruck->fresh()->is_available)->toBeFalse()
            ->and($other->truck->fresh()->is_available)->toBeFalse()->and($other->fresh()->truck_id)->toBe($other->truck_id);
    });

    test('an update reorders, keeps, adds and drops checkpoints as posted', function () {
        $trip = createTripTestRecord();
        $first = createTripTestCheckpoint($trip, ['notes' => 'First']);
        $dropped = createTripTestCheckpoint($trip, ['sort_order_in_trip' => 2, 'notes' => 'Dropped']);
        $customer = createStoreUser();
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.update', $trip), [...tripTestPayload($trip->truck), 'checkpoints_array' => [
            ['id' => 0, 'checkpoint_type' => 'other', 'checkpoint_related_id' => $customer->id, 'checkpoint_notes' => 'New stop'],
            ['id' => $first->id, 'checkpoint_type' => 'other', 'checkpoint_related_id' => 9, 'checkpoint_notes' => 'Moved'],
        ]])->assertRedirect(route('trips.show', $trip->id));

        $rows = $trip->checkpoints()->get(['id', 'relationable_id', 'sort_order_in_trip', 'notes']);
        expect($rows->pluck('relationable_id')->all())->toBe([$customer->id, 9])
            ->and($rows->pluck('sort_order_in_trip')->all())->toBe([1, 2])
            ->and($rows[1]->id)->toBe($first->id)->and($rows[1]->notes)->toBe('Moved')
            ->and(Checkpoint::find($dropped->id))->toBeNull();
    });
});

test('map creation fails departure validation before writing any rows', function () {
    $this->departure->delete();
    $truck = Truck::create(['name' => 'Map truck']);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    actingAsStoreUser(createStoreStaff('add_trip'));

    $this->postJson(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]])->assertUnprocessable()->assertJsonValidationErrors('departure');

    expect(Trip::count())->toBe(0)->and(Checkpoint::count())->toBe(0)->and($truck->fresh()->is_available)->toBeTrue();
});

test('closed trip edits do not reserve the truck again', function () {
    $trip = createTripTestRecord(['status' => 'closed', 'active' => false, 'closed_at' => '2026-10-09 10:00:00']);
    $trip->truck->update(['is_available' => true]);
    $checkpoint = createTripTestCheckpoint($trip);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), tripTestPayload($trip->truck, $checkpoint))->assertRedirect();

    expect($trip->fresh()->status)->toBe('closed')->and($trip->fresh()->active)->toBeFalse()->and($trip->truck->fresh()->is_available)->toBeTrue();
});

test('map route geometry comes from HTTPS routing and uses only valid points', function () {
    $this->routingResponse = Http::response(['routes' => [['geometry' => ['coordinates' => [[0, 0], [4.2, 52.1]]]]]]);
    $trip = createTripTestRecord();
    $first = createTripTestCheckpoint($trip, ['latitudes' => '0', 'longitudes' => '0']);
    $second = createTripTestCheckpoint($trip, ['sort_order_in_trip' => 2]);
    $invalid = createTripTestCheckpoint($trip, ['latitudes' => null, 'sort_order_in_trip' => 3]);
    $controller = app(TripController::class);

    $result = (new ReflectionMethod($controller, 'getCheckpointsMapData'))->invoke($controller, collect([$first, $second, $invalid]));

    expect($result['coordinates'])->toBe([[0, 0], [4.2, 52.1]])->and($result['long_lat_array'])->toHaveCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://router.project-osrm.org/route/v1/driving/0,0;4.2,52.1'));
});

test('shortest path sorting maps waypoint indexes to checkpoint identities with nearby coordinates', function () {
    $this->routingResponse = Http::response([
        'waypoints' => [['waypoint_index' => 1], ['waypoint_index' => 0]],
        'trips' => [['duration' => 120, 'distance' => 1000, 'legs' => [['duration' => 40], ['duration' => 80]]]],
    ]);
    $trip = createTripTestRecord();
    $first = createTripTestCheckpoint($trip, ['longitudes' => '4.211', 'latitudes' => '52.111', 'cmr_file' => '17']);
    $second = createTripTestCheckpoint($trip, ['longitudes' => '4.212', 'latitudes' => '52.112', 'sort_order_in_trip' => 2, 'photos' => '18']);
    $controller = app(TripController::class);

    (new ReflectionMethod($controller, 'sortBasedOnShortestPath'))->invoke($controller, $trip);

    expect($first->fresh()->sort_order_in_trip)->toBe(2)->and($second->fresh()->sort_order_in_trip)->toBe(1)
        ->and($first->fresh()->eta)->toBe(80.0)->and($second->fresh()->eta)->toBe(40.0)
        ->and($first->fresh()->cmr_file)->toBe('17')->and($second->fresh()->photos)->toBe('18')
        ->and($first->fresh()->longitudes)->toBe('4.211')->and($second->fresh()->latitudes)->toBe('52.112')
        ->and($trip->fresh()->duration)->toBe('120.00')->and($trip->fresh()->distance)->toBe('1000.00');
});

test('trip checkpoint screens display empty trips safely', function () {
    $trip = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('view_all_trips'));

    $this->get(route('trips.show', $trip->id))->assertOk()->assertViewIs('shop.backend.trips.checkpoints.index')->assertSee('No Checkpoints found on this Trip')->assertViewHas('long_lat_array', []);

    Http::assertNothingSent();
});

test('checkpoint write controls require edit trip while readers retain notes and map labels', function (bool $canEdit) {
    seedStoreCurrency();
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip, ['notes' => 'Visible checkpoint note']);
    createTripTestCheckpoint($trip, ['relationable_id' => createStoreUser()->id, 'sort_order_in_trip' => 2]);
    actingAsStoreUser(createStoreStaff('view_all_trips', ...($canEdit ? ['edit_trip'] : [])));

    $response = $this->get(route('trips.show', $trip->id))->assertOk()->assertSee('Visible checkpoint note');
    if ($canEdit) {
        $response->assertSee('data-checkpoint-id="'.$checkpoint->id.'"', false)->assertSee('class="form-control checkpoint-notes"', false)->assertSee('onclick="openUpdateModal(this)"', false);
    } else {
        $response->assertDontSee('class="form-control checkpoint-notes"', false)->assertDontSee('onclick="openUpdateModal(this)"', false);
    }
    $response->assertSee('tripMapPopup([[null, marker.label]])', false);
})->with([false, true]);

test('the retained map picker renders a busy truck with no open trip without a broken link', function () {
    Truck::create(['name' => 'Busy without trip', 'is_available' => false]);
    actingAsStoreUser(createStoreStaff('add_trip'));

    $this->get(route('trips.create-from-map'))->assertOk()->assertViewIs('shop.backend.trips.create-from-map')->assertSee('Busy without trip')->assertSee('No current trip information')->assertDontSee('confirm-delete', false);
});

test('trip checkpoint and map picker screens refuse unauthorized callers', function (string $caller) {
    $trip = createTripTestRecord();
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get(route('trips.show', $trip->id)), $caller);
    assertStoreCallerRefused($this->get(route('trips.create-from-map')), $caller);
    assertStoreCallerRefused($this->get(route('trips.index')), $caller);
})->with('refused store admin callers');

test('editing a closed trip preserves a truck now assigned to another open trip', function () {
    $trip = createTripTestRecord(['status' => 'closed', 'active' => false, 'closed_at' => '2026-10-09 10:00:00']);
    $checkpoint = createTripTestCheckpoint($trip);
    $current = createTripTestRecord(['truck_id' => $trip->truck_id]);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->put(route('trips.update', $trip), tripTestPayload($trip->truck, $checkpoint))->assertRedirect();

    expect($trip->fresh()->status)->toBe('closed')->and($trip->fresh()->active)->toBeFalse()
        ->and($trip->fresh()->notes)->toBe('Trip note')->and($trip->truck->fresh()->is_available)->toBeFalse()
        ->and($current->fresh()->status)->toBe('pending')->and($current->fresh()->truck_id)->toBe($trip->truck_id);
});

describe('trip lists', function () {
    test('the all-trips list renders every row field', function () {
        $driver = createStoreUser('driver');
        $driver->forceFill(['name' => 'Sam Driver'])->save();
        $trip = createTripTestRecord(['driver_id' => $driver->id, 'status' => 'started', 'started_at' => '2026-10-10 09:05:00', 'closed_at' => '2026-10-11 17:45:00', 'distance' => 12345.67, 'duration' => 3720, 'notes' => 'Visible note']);
        $bare = createTripTestRecord(['truck_id' => null, 'due_date' => null]);
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/Index')
            ->where('listType', 'all')
            ->where('filters', ['per_page' => 20, 'search' => '', 'status' => []])
            ->where('statuses', ['pending', 'started', 'closed'])
            ->has('trips.data', 2)
            ->has('trips.data.0', fn (Assert $row) => $row
                ->where('id', $bare->id)->where('code', $bare->code)->where('status', 'pending')
                ->where('due_date', null)->where('started_at', null)->where('closed_at', null)
                ->where('distance', null)->where('duration', null)->where('notes', null)
                ->where('driver', null)->where('truck', null))
            ->has('trips.data.1', fn (Assert $row) => $row
                ->where('id', $trip->id)->where('code', $trip->code)->where('status', 'started')
                ->where('due_date', '2026-10-10')->where('started_at', '2026-10-10T09:05:00+00:00')->where('closed_at', '2026-10-11T17:45:00+00:00')
                ->where('distance', 12345.67)->where('duration', 3720)->where('notes', 'Visible note')
                ->where('driver', ['id' => $driver->id, 'name' => 'Sam Driver'])
                ->where('truck', ['id' => $trip->truck_id, 'name' => 'Delivery truck'])));
    });

    test('today\'s list shows only trips due today and both lists filter by status and search', function () {
        $this->travelTo('2026-10-14 08:00:00');
        $today = createTripTestRecord(['due_date' => '2026-10-14']);
        $todayStarted = createTripTestRecord(['due_date' => '2026-10-14', 'status' => 'started']);
        $tomorrow = createTripTestRecord(['due_date' => '2026-10-15']);
        $closed = createTripTestRecord(['status' => 'closed', 'active' => false]);
        $driver = createStoreUser('driver');
        $driver->forceFill(['name' => 'Sam Driver'])->save();
        $driven = createTripTestRecord(['driver_id' => $driver->id]);
        actingAsStoreUser(createStoreStaff('view_all_trips'));
        $ids = fn (array $query, string $route = 'trips.index') => array_column($this->get(route($route, $query))->assertOk()->inertiaProps('trips.data'), 'id');

        $this->get(route('trips.today-trips'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Trips/Trips/Index')->where('listType', 'today'));
        expect($ids([], 'trips.today-trips'))->toBe([$todayStarted->id, $today->id])
            ->and($ids(['status' => ['started']], 'trips.today-trips'))->toBe([$todayStarted->id])
            ->and($ids(['status' => ['pending', 'closed']]))->toBe([$driven->id, $closed->id, $tomorrow->id, $today->id])
            ->and($ids(['search' => $tomorrow->code]))->toBe([$tomorrow->id])
            ->and($ids(['search' => 'Sam Dri']))->toBe([$driven->id]);
        $this->get(route('trips.index', ['search' => 'Sam Dri', 'status' => ['pending']]))->assertInertia(fn (Assert $page) => $page->where('filters', ['per_page' => 20, 'search' => 'Sam Dri', 'status' => ['pending']]));
    });

    test('trips paginate twenty at a time with an allowed page size and refuse other filters', function () {
        for ($i = 0; $i < 21; $i++) {
            createTripTestRecord();
        }
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.index'))->assertInertia(fn (Assert $page) => $page->has('trips.data', 20)->where('trips.meta.total', 21)->where('trips.meta.last_page', 2));
        $this->get(route('trips.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('trips.data', 1));
        $this->get(route('trips.index', ['per_page' => 10, 'search' => 'Trip']))->assertInertia(fn (Assert $page) => $page->has('trips.data', 10)->where('filters.per_page', 10));
        $this->get(route('trips.index', ['per_page' => 999]))->assertSessionHasErrors('per_page');
        $this->get(route('trips.index', ['search' => ['bad']]))->assertSessionHasErrors('search');
        $this->get(route('trips.index', ['status' => ['destroyed']]))->assertSessionHasErrors('status.0');
        $this->get(route('trips.today-trips', ['status' => 'pending']))->assertSessionHasErrors('status');
    });

    test('the trip lists refuse callers without permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('trips.index')), $caller);
        assertStoreCallerRefused($this->get(route('trips.today-trips')), $caller);
    })->with('refused store admin callers');

    test('each trip list action needs its own permission', function (string $method, string $route, string $permission) {
        $trip = createTripTestRecord();
        $url = match ($route) {
            'trips.index', 'trips.today-trips' => route($route),
            'trips.bulk-trip-delete' => route($route, ['list' => 'all']),
            default => route($route, [$trip, 'list' => 'all']),
        };
        $payload = ['status' => 'started', 'notes' => 'Changed', 'id' => [$trip->id]];
        actingAsStoreUser(createStoreStaff(...array_values(array_diff(['view_all_trips', 'edit_trip', 'delete_trip', 'delete_customer'], [$permission]))));

        $this->call($method, $url, $payload)->assertForbidden();
        expect($trip->fresh()->only(['status', 'notes']))->toBe(['status' => 'pending', 'notes' => null]);

        actingAsStoreUser(createStoreStaff($permission));
        expect($this->call($method, $url, $payload)->getStatusCode())->toBeIn([200, 302]);
    })->with([
        'all trips' => ['GET', 'trips.index', 'view_all_trips'],
        'today\'s trips' => ['GET', 'trips.today-trips', 'view_all_trips'],
        'status' => ['PUT', 'trips.update-status', 'edit_trip'],
        'notes' => ['PUT', 'trips.update-notes', 'edit_trip'],
        'delete' => ['DELETE', 'trips.destroy', 'delete_trip'],
        'bulk delete' => ['POST', 'trips.bulk-trip-delete', 'delete_trip'],
    ]);
});

describe('trip notes', function () {
    test('notes save from a list and return to it with its filters', function () {
        $trip = createTripTestRecord(['notes' => 'Old note']);
        $noise = createTripTestRecord(['notes' => 'Noise note']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.update-notes', [$trip, 'list' => 'today', 'page' => 2]), ['notes' => 'New note'])->assertRedirect(route('trips.today-trips', ['page' => 2]));
        expect($trip->fresh()->notes)->toBe('New note')->and($noise->fresh()->notes)->toBe('Noise note');

        $this->put(route('trips.update-notes', [$trip, 'list' => 'all']), ['notes' => ''])->assertRedirect(route('trips.index'));
        expect($trip->fresh()->notes)->toBeNull()->and($trip->fresh()->status)->toBe('pending');
    });

    test('notes follow the edit form\'s rule and need a list and an existing trip', function () {
        $trip = createTripTestRecord(['notes' => 'Old note']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.update-notes', [$trip, 'list' => 'all']), ['notes' => str_repeat('x', 256)])->assertSessionHasErrors('notes');
        $this->put(route('trips.update-notes', [$trip, 'list' => 'all']), ['notes' => ['bad']])->assertSessionHasErrors('notes');
        $this->put(route('trips.update-notes', $trip), ['notes' => 'New'])->assertSessionHasErrors('list');
        $this->put(route('trips.update-notes', ['trip' => 999999, 'list' => 'all']), ['notes' => 'New'])->assertNotFound();
        $this->put(route('trips.update-notes', [$trip, 'list' => 'all']), ['notes' => str_repeat('x', 255)])->assertRedirect(route('trips.index'));

        expect($trip->fresh()->notes)->toBe(str_repeat('x', 255));
    });

    test('notes refuse callers and change nothing', function (string $caller) {
        $trip = createTripTestRecord(['notes' => 'Old note']);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->put(route('trips.update-notes', [$trip, 'list' => 'all']), ['notes' => 'Changed']), $caller);

        expect($trip->fresh()->notes)->toBe('Old note');
    })->with('refused store admin callers');
});

describe('trip deletion', function () {
    test('deleting a trip removes it and its checkpoints, keeps its orders\' status and frees its truck', function () {
        $trip = createTripTestRecord();
        $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
        $noise = createTripTestRecord();
        $noiseCheckpoint = createTripTestCheckpoint($noise);
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->delete(route('trips.destroy', [$trip, 'list' => 'all', 'page' => 2, 'search' => 'Trip']))->assertRedirect(route('trips.index', ['page' => 2, 'search' => 'Trip']));

        expect(Trip::find($trip->id))->toBeNull()->and(Checkpoint::where('trip_id', $trip->id)->count())->toBe(0)
            ->and($order->fresh()->delivery_status)->toBe('on_delivery')
            ->and($trip->truck->fresh()->is_available)->toBeTrue()
            ->and($noise->fresh())->not->toBeNull()->and($noiseCheckpoint->fresh())->not->toBeNull()
            ->and($noise->truck->fresh()->is_available)->toBeFalse();
    });

    test('deleting a trip keeps its truck reserved while another open trip holds it', function () {
        $trip = createTripTestRecord();
        $other = createTripTestRecord(['truck_id' => $trip->truck_id]);
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->delete(route('trips.destroy', [$trip, 'list' => 'today']))->assertRedirect(route('trips.today-trips'));

        expect(Trip::find($trip->id))->toBeNull()->and($other->fresh())->not->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
    });

    test('the map picker deletes a truck\'s open trip through a spoofed DELETE and returns to the picker', function () {
        $trip = createTripTestRecord();
        actingAsStoreUser(createStoreStaff('add_trip', 'delete_trip'));
        $link = route('trips.destroy', ['trip' => $trip->id, 'list' => 'map']);

        $this->get(route('trips.create-from-map'))->assertOk()->assertSee('data-method="delete" data-href="'.e($link).'"', false);
        $this->post($link, ['_method' => 'DELETE'])->assertRedirect(route('trips.create-from-map'));

        expect(Trip::find($trip->id))->toBeNull()->and($trip->truck->fresh()->is_available)->toBeTrue();
    });

    test('deleting needs a list and an existing trip and is never a GET or a POST', function () {
        $trip = createTripTestRecord();
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->delete(route('trips.destroy', $trip))->assertSessionHasErrors('list');
        $this->delete(route('trips.destroy', [$trip, 'list' => 'seller']))->assertSessionHasErrors('list');
        $this->delete(route('trips.destroy', ['trip' => 999999, 'list' => 'all']))->assertNotFound();
        $this->get(route('trips.destroy', [$trip, 'list' => 'all']))->assertMethodNotAllowed();
        $this->post(route('trips.destroy', [$trip, 'list' => 'all']))->assertMethodNotAllowed();
        $this->post('/temp/admin/trips/delete/'.$trip->id)->assertNotFound();

        expect($trip->fresh())->not->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
    });

    test('deleting a trip refuses callers and changes nothing', function (string $caller) {
        $trip = createTripTestRecord();
        createTripTestCheckpoint($trip);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->delete(route('trips.destroy', [$trip, 'list' => 'all'])), $caller);

        expect($trip->fresh())->not->toBeNull()->and(Checkpoint::where('trip_id', $trip->id)->count())->toBe(1)->and($trip->truck->fresh()->is_available)->toBeFalse();
    })->with('refused store admin callers');

    test('bulk deleting removes only the selected trips and their checkpoints and frees their trucks', function () {
        $first = createTripTestRecord();
        $second = createTripTestRecord();
        $kept = createTripTestRecord();
        createTripTestCheckpoint($first);
        createTripTestCheckpoint($kept);
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->post(route('trips.bulk-trip-delete', ['list' => 'today', 'search' => 'Trip']), ['id' => [$first->id, $second->id]])->assertRedirect(route('trips.today-trips', ['search' => 'Trip']));

        expect(Trip::pluck('id')->all())->toBe([$kept->id])->and(Checkpoint::pluck('trip_id')->all())->toBe([$kept->id])
            ->and($first->truck->fresh()->is_available)->toBeTrue()->and($second->truck->fresh()->is_available)->toBeTrue()
            ->and($kept->truck->fresh()->is_available)->toBeFalse();
    });

    test('bulk deleting validates the selection and its list', function (array $query, array $payload, string $error) {
        $trip = createTripTestRecord();
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->post(route('trips.bulk-trip-delete', $query), $payload)->assertSessionHasErrors($error);

        expect(Trip::count())->toBe(1)->and($trip->truck->fresh()->is_available)->toBeFalse();
    })->with([
        'no selection' => [['list' => 'all'], [], 'id'],
        'empty selection' => [['list' => 'all'], ['id' => []], 'id'],
        'unknown trip' => [['list' => 'all'], ['id' => [999999]], 'id.0'],
        'not a number' => [['list' => 'all'], ['id' => ['x']], 'id.0'],
        'no list' => [[], ['id' => [999999]], 'list'],
    ]);

    test('a selection with one unknown or repeated trip deletes none of them', function () {
        $trip = createTripTestRecord();
        createTripTestCheckpoint($trip);
        actingAsStoreUser(createStoreStaff('delete_trip'));

        $this->post(route('trips.bulk-trip-delete', ['list' => 'all']), ['id' => [$trip->id, 999999]])->assertSessionHasErrors('id.1');
        $this->post(route('trips.bulk-trip-delete', ['list' => 'all']), ['id' => [$trip->id, $trip->id]])->assertSessionHasErrors('id.0');

        expect($trip->fresh())->not->toBeNull()->and(Checkpoint::count())->toBe(1)->and($trip->truck->fresh()->is_available)->toBeFalse();
    });

    test('bulk deleting refuses callers and deletes nothing', function (string $caller) {
        $trip = createTripTestRecord();
        createTripTestCheckpoint($trip);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->post(route('trips.bulk-trip-delete', ['list' => 'all']), ['id' => [$trip->id]]), $caller);

        expect(Trip::count())->toBe(1)->and(Checkpoint::count())->toBe(1)->and($trip->truck->fresh()->is_available)->toBeFalse();
    })->with('refused store admin callers');
});
