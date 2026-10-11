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

test('editing and updating a missing trip return 404', function () {
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->get(route('trips.edit', 999999))->assertNotFound();
    $this->post(route('trips.update'), ['id' => 999999])->assertNotFound();
});

test('trip updates preserve checkpoint identity coordinates status arrival and attachments', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip, ['status' => 'delivered', 'arrived_at' => '2026-10-09 09:00:00', 'cmr_file' => '17', 'photos' => '18,19', 'address' => ['city' => 'Rotterdam']]);
    $noise = createTripTestCheckpoint(createTripTestRecord());
    $newTruck = Truck::create(['name' => 'Replacement truck']);
    $before = $checkpoint->only(['trip_id', 'relationable_id', 'relationable_type', 'type', 'longitudes', 'latitudes', 'status', 'arrived_at', 'cmr_file', 'photos', 'address']);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($newTruck, $checkpoint)])->assertRedirect(route('trips.show', $trip->id));

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

    assertStoreCallerRefused($this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($trip->truck, $checkpoint)]), $caller);

    expect($trip->fresh()->notes)->toBeNull()->and($checkpoint->fresh()->notes)->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
})->with('refused store admin callers');

test('foreign checkpoint ids cannot be copied into or removed from another trip', function () {
    $trip = createTripTestRecord();
    $own = createTripTestCheckpoint($trip);
    $foreign = createTripTestCheckpoint(createTripTestRecord());
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($trip->truck, $foreign)])->assertSessionHasErrors('checkpoints_array.0.id');

    expect($own->fresh())->not->toBeNull()->and($foreign->fresh()->trip_id)->toBe($foreign->trip_id)->and($trip->fresh()->notes)->toBeNull();
});

test('bound checkpoints cannot be reassigned to a different customer', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip);
    $payload = tripTestPayload($trip->truck, $checkpoint);
    $payload['checkpoints_array'][0]['checkpoint_related_id'] = createStoreUser()->id;
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update'), ['id' => $trip->id, ...$payload])->assertSessionHasErrors('checkpoints_array.0.checkpoint_related_id');

    expect($checkpoint->fresh()->relationable_id)->toBe(9)->and($trip->fresh()->notes)->toBeNull();
});

test('trip updates cannot take a truck from another open trip', function () {
    $trip = createTripTestRecord();
    $checkpoint = createTripTestCheckpoint($trip);
    $other = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($other->truck, $checkpoint)])->assertSessionHasErrors('truck_id');

    expect($trip->fresh()->truck_id)->toBe($trip->truck_id)->and($other->fresh()->truck_id)->toBe($other->truck_id)->and($checkpoint->fresh()->notes)->toBeNull();
});

test('every existing trip transition keeps dates active state orders and truck availability consistent', function (string $from, string $to) {
    $trip = createTripTestRecord(['status' => $from, 'active' => $from !== 'closed', 'started_at' => $from === 'started' ? '2026-10-09 09:00:00' : null, 'closed_at' => $from === 'closed' ? '2026-10-09 12:00:00' : null]);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => $to])->assertOk()->assertSee('1');

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

    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'started'])->assertOk();
    $this->post(route('checkpoints.update-status'), ['id' => $checkpoint->id, 'status' => 'delivered'])->assertOk();
    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'closed'])->assertOk();
    $closedAt = $trip->fresh()->closed_at;
    $this->travel(1)->hours();
    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'closed'])->assertOk();

    expect($trip->fresh()->started_at->toDateTimeString())->toBe('2026-10-09 09:00:00')
        ->and($checkpoint->fresh()->arrived_at->toDateTimeString())->toBe('2026-10-09 10:00:00')
        ->and($trip->fresh()->closed_at)->toEqual($closedAt);
});

test('reopening a closed trip refuses to take a truck now owned by another open trip', function () {
    $trip = createTripTestRecord(['status' => 'closed', 'active' => false, 'closed_at' => '2026-10-09 12:00:00']);
    $other = createTripTestRecord(['truck_id' => $trip->truck_id]);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'started'])->assertSessionHasErrors('truck_id');

    expect($trip->fresh()->status)->toBe('closed')->and($trip->fresh()->active)->toBeFalse()->and($other->fresh()->status)->toBe('pending');
});

test('trip status transitions refuse callers and leave timestamps status orders and truck unchanged', function (string $caller) {
    $trip = createTripTestRecord();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    createTripTestCheckpoint($trip, ['type' => 'delivery', 'relationable_type' => Order::class, 'relationable_id' => $order->id]);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'closed']), $caller);

    expect($trip->fresh()->status)->toBe('pending')->and($trip->fresh()->closed_at)->toBeNull()->and($trip->fresh()->active)->toBeTrue()->and($trip->truck->fresh()->is_available)->toBeFalse()->and($order->fresh()->delivery_status)->toBe('on_delivery');
})->with('refused store admin callers');

test('trip status validation rejects invalid enums and unknown trips', function () {
    $trip = createTripTestRecord();
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->post(route('trips.update-status'), ['id' => $trip->id, 'status' => 'destroyed'])->assertSessionHasErrors('status');
    $this->post(route('trips.update-status'), ['id' => 999999, 'status' => 'closed'])->assertNotFound();

    expect($trip->fresh()->status)->toBe('pending')->and($trip->fresh()->closed_at)->toBeNull()->and($trip->truck->fresh()->is_available)->toBeFalse();
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

test('the existing trip edit screen can be rendered', function () {
    $trip = createTripTestRecord();
    createTripTestCheckpoint($trip);
    actingAsStoreUser(createStoreStaff('edit_trip'));

    $this->get(route('trips.edit', $trip->id))->assertOk()->assertViewIs('shop.backend.trips.edit')->assertViewHas('trip', fn ($record) => $record->id === $trip->id);
});

test('trip editing refuses every unauthorized caller', function (string $caller) {
    $trip = createTripTestRecord();
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get(route('trips.edit', $trip->id)), $caller);
})->with('refused store admin callers');

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

    $this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($trip->truck, $checkpoint)])->assertRedirect();

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

test('trip index write controls follow edit trip and delete trip permissions', function (array $permissions, bool $canEdit, bool $canDelete) {
    $trip = createTripTestRecord(['notes' => 'Visible note']);
    createTripTestRecord();
    actingAsStoreUser(createStoreStaff('view_all_trips', ...$permissions));

    $response = $this->get(route('trips.index'))->assertOk()->assertViewIs('shop.backend.trips.index')->assertSee('Visible note');
    if ($canEdit) {
        $response->assertSee('class="form-control trip-notes"', false)->assertSee('onclick="openUpdateModal(this)"', false)->assertSee(route('trips.edit', $trip->id), false);
    } else {
        $response->assertDontSee('class="form-control trip-notes"', false)->assertDontSee('onclick="openUpdateModal(this)"', false)->assertDontSee(route('trips.edit', $trip->id), false);
    }
    if ($canDelete) {
        $response->assertSee(route('trips.destroy', $trip->id), false)->assertSee('onclick="bulk_delete()"', false);
    } else {
        $response->assertDontSee(route('trips.destroy', $trip->id), false)->assertDontSee('onclick="bulk_delete()"', false);
    }
})->with([
    'reader' => [[], false, false],
    'editor' => [['edit_trip'], true, false],
    'trip deleter' => [['delete_trip'], false, true],
    'customer deleter' => [['delete_customer'], false, false],
]);

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

    $this->post(route('trips.update'), ['id' => $trip->id, ...tripTestPayload($trip->truck, $checkpoint)])->assertRedirect();

    expect($trip->fresh()->status)->toBe('closed')->and($trip->fresh()->active)->toBeFalse()
        ->and($trip->fresh()->notes)->toBe('Trip note')->and($trip->truck->fresh()->is_available)->toBeFalse()
        ->and($current->fresh()->status)->toBe('pending')->and($current->fresh()->truck_id)->toBe($trip->truck_id);
});
