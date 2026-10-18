<?php

use App\Http\Controllers\Store\Admin\TripMapController;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// The coordinate lookup answers per store user (default: the depot's spot);
// the routing service is down, so the posted stop order is kept.
beforeEach(function () {
    Http::fake(fn () => Http::response([], 503));
    $this->coordinates = [];
    $this->mock(UserService::class)->shouldReceive('getUserLongLat')->andReturnUsing(
        fn (?StoreUser $user = null) => $this->coordinates[$user?->id] ?? ['latitude' => '52.1', 'longitude' => '4.2', 'postal_code' => '1234']
    );
    $this->notifications = $this->mock(DriverService::class);
    $this->notifications->shouldReceive('notifyDriver');
    $this->departure = createStoreUser()->forceFill(['id' => 9]);
    $this->departure->save();
});

function mapPickerTrip(Truck $truck, array $attributes = []): Trip
{
    return Trip::create(['code' => 'Trip-'.random_int(1, 99999999), 'truck_id' => $truck->id, 'due_date' => '2026-10-10', 'status' => 'pending', 'active' => true, ...$attributes])->fresh();
}

function mapPickerOrder(array $attributes = []): Order
{
    return createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery', ...$attributes]);
}

/**
 * Every trip, checkpoint, truck availability and order delivery status, to
 * assert that a refused or invalid write changed nothing.
 *
 * @return array<string, mixed>
 */
function mapPickerState(): array
{
    return [
        'trips' => Trip::query()->orderBy('id')->get(['id', 'truck_id', 'driver_id', 'status'])->toArray(),
        'checkpoints' => Checkpoint::query()->orderBy('id')->get(['id', 'trip_id', 'relationable_type', 'relationable_id', 'sort_order_in_trip'])->toArray(),
        'trucks' => Truck::query()->orderBy('id')->pluck('is_available', 'id')->all(),
        'orders' => Order::query()->orderBy('id')->pluck('delivery_status', 'id')->all(),
    ];
}

describe('map picker page', function () {
    test('the picker renders every located order, the free and the busy trucks with their open trips and photos', function () {
        $alma = createStoreUser();
        $alma->forceFill(['name' => 'Alma Customer', 'phone' => '0611111111', 'AccSysID' => 'ACC-7', 'shipping_address' => 'Alma street 1'])->save();
        $bram = createStoreUser();
        $bram->forceFill(['name' => 'Bram Customer', 'phone' => '0622222222', 'AccSysID' => null, 'shipping_address' => 'Bram street 2'])->save();
        $rep = createStoreUser();
        $rep->forceFill(['name' => 'Rita Rep'])->save();
        $unlocated = createStoreUser();
        $outOfRange = createStoreUser();
        $this->coordinates = [
            $alma->id => ['latitude' => '52.37', 'longitude' => '4.89', 'postal_code' => '1011AB'],
            $bram->id => ['latitude' => 0, 'longitude' => 0, 'postal_code' => null],
            $unlocated->id => ['latitude' => null, 'longitude' => null, 'postal_code' => null],
            $outOfRange->id => ['latitude' => '91', 'longitude' => '4.89', 'postal_code' => '1011AB'],
        ];
        $own = createStoreOrder($alma, ['code' => '20261017-0001', 'delivery_status' => 'on_delivery', 'shipping_address' => json_encode(['address' => 'Dock road 5', 'country' => 'Netherlands'])]);
        $forCustomer = createStoreOrder($rep, ['code' => '20261017-0002', 'delivery_status' => 'on_delivery', 'by_rep' => 1, 'for_customer' => $bram->id, 'shipping_address' => '']);
        backdate($own, '2026-10-17 08:00:00');
        backdate($forCustomer, '2026-10-17 09:00:00');
        createStoreOrder($alma, ['delivery_status' => 'pending']);
        createStoreOrder($alma, ['delivery_status' => 'delivered']);
        createStoreOrder($unlocated, ['delivery_status' => 'on_delivery']);
        createStoreOrder($outOfRange, ['delivery_status' => 'on_delivery']);

        $photo = Upload::factory()->create(['file_original_name' => 'cargo.jpg', 'type' => 'image', 'external_link' => 'https://cdn.example.com/cargo.jpg']);
        $busyPhoto = Upload::factory()->create(['file_original_name' => 'busy.jpg', 'type' => 'image', 'external_link' => 'https://cdn.example.com/busy.jpg']);
        $second = Upload::factory()->create(['file_original_name' => 'second.jpg', 'type' => 'image', 'external_link' => 'https://cdn.example.com/second.jpg']);
        Upload::factory()->create(['file_original_name' => 'noise.jpg', 'type' => 'image']);
        $free = Truck::create(['name' => 'Free truck', 'is_available' => true, 'max_pallets_number' => '18', 'photos' => "{$photo->id},{$second->id}"]);
        $plain = Truck::create(['name' => 'Plain truck', 'is_available' => true, 'photos' => 'x']);
        $busy = Truck::create(['name' => 'Busy truck', 'is_available' => false, 'max_pallets_number' => '7', 'photos' => (string) $busyPhoto->id]);
        $idle = Truck::create(['name' => 'Idle busy truck', 'is_available' => false]);
        $driver = createStoreUser('driver');
        $driver->forceFill(['name' => 'Sam Driver'])->save();
        $older = mapPickerTrip($busy, ['code' => 'OLDER']);
        backdate($older, '2026-10-16 08:00:00');
        $latest = mapPickerTrip($busy, ['code' => 'LATEST', 'driver_id' => $driver->id, 'status' => 'started']);
        backdate($latest, '2026-10-17 08:00:00');
        backdate(mapPickerTrip($busy, ['code' => 'CLOSED', 'status' => 'closed']), '2026-10-17 10:00:00');
        $departure = $latest->checkpoints()->create(['relationable_type' => StoreUser::class, 'relationable_id' => 9, 'type' => 'other', 'status' => 'pending', 'sort_order_in_trip' => 1]);
        $delivery = $latest->checkpoints()->create(['relationable_type' => Order::class, 'relationable_id' => createStoreOrder($rep, ['by_rep' => 1, 'for_customer' => $alma->id])->id, 'type' => 'delivery', 'status' => 'pending', 'sort_order_in_trip' => 3]);
        $gone = $latest->checkpoints()->create(['relationable_type' => Order::class, 'relationable_id' => 999999, 'type' => 'delivery', 'status' => 'pending', 'sort_order_in_trip' => 2]);
        $olderStop = $older->checkpoints()->create(['relationable_type' => StoreUser::class, 'relationable_id' => $bram->id, 'type' => 'other', 'status' => 'pending', 'sort_order_in_trip' => 1]);
        actingAsStoreUser(createStoreStaff('add_trip'));

        $this->get(route('trips.create-from-map'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/CreateFromMap')
            ->where('orders', [
                [
                    'id' => $forCustomer->id, 'code' => '20261017-0002', 'latitude' => 0, 'longitude' => 0, 'postal_code' => null, 'address' => 'Bram street 2',
                    'customer' => ['id' => $bram->id, 'name' => 'Bram Customer', 'phone' => '0622222222', 'accounting_id' => null],
                ],
                [
                    'id' => $own->id, 'code' => '20261017-0001', 'latitude' => 52.37, 'longitude' => 4.89, 'postal_code' => '1011AB', 'address' => 'Dock road 5',
                    'customer' => ['id' => $alma->id, 'name' => 'Alma Customer', 'phone' => '0611111111', 'accounting_id' => 'ACC-7'],
                ],
            ])
            ->where('availableTrucks', [
                ['id' => $free->id, 'name' => 'Free truck', 'max_pallets_number' => '18', 'photo' => $photo->id, 'trip' => null, 'open_trips' => []],
                ['id' => $plain->id, 'name' => 'Plain truck', 'max_pallets_number' => null, 'photo' => null, 'trip' => null, 'open_trips' => []],
            ])
            ->where('busyTrucks', [
                [
                    'id' => $busy->id, 'name' => 'Busy truck', 'max_pallets_number' => '7', 'photo' => $busyPhoto->id,
                    'trip' => ['id' => $latest->id, 'code' => 'LATEST', 'driver' => ['id' => $driver->id, 'name' => 'Sam Driver']],
                    'open_trips' => [
                        ['id' => $latest->id, 'code' => 'LATEST', 'stops' => [
                            ['id' => $departure->id, 'customer' => ['id' => 9, 'name' => $this->departure->name]],
                            ['id' => $gone->id, 'customer' => null],
                            ['id' => $delivery->id, 'customer' => ['id' => $alma->id, 'name' => 'Alma Customer']],
                        ]],
                        ['id' => $older->id, 'code' => 'OLDER', 'stops' => [
                            ['id' => $olderStop->id, 'customer' => ['id' => $bram->id, 'name' => 'Bram Customer']],
                        ]],
                    ],
                ],
                ['id' => $idle->id, 'name' => 'Idle busy truck', 'max_pallets_number' => null, 'photo' => null, 'trip' => null, 'open_trips' => []],
            ])
            ->where('photos', [
                ['id' => $photo->id, 'name' => 'cargo.jpg', 'url' => 'https://cdn.example.com/cargo.jpg'],
                ['id' => $busyPhoto->id, 'name' => 'busy.jpg', 'url' => 'https://cdn.example.com/busy.jpg'],
            ]));

        Http::assertNothingSent();
    });

    test('the picker renders empty without orders or trucks', function () {
        actingAsStoreUser(createStoreStaff('add_trip'));

        $this->get(route('trips.create-from-map'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/CreateFromMap')
            ->where('orders', [])->where('availableTrucks', [])->where('busyTrucks', [])->where('photos', []));
    });

    test('the picker refuses unauthorized callers', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('trips.create-from-map')), $caller);
    })->with('refused store admin callers');

    test('the routes keep their names and permission on the store controller, and the root controller and Blade views are gone', function () {
        expect(Route::getRoutes()->getByName('trips.create-from-map')->getActionName())->toBe(TripMapController::class.'@create')
            ->and(Route::getRoutes()->getByName('trips.store-from-map')->getActionName())->toBe(TripMapController::class.'@store')
            ->and(Route::getRoutes()->getByName('trips.create-from-map')->uri())->toBe('temp/admin/trips/create-from-map')
            ->and(Route::getRoutes()->getByName('trips.store-from-map')->methods())->toBe(['POST'])
            ->and(TripMapController::middleware()[0]->middleware)->toBe('permission:add_trip')
            ->and(class_exists('App\Http\Controllers\Trip\TripController'))->toBeFalse()
            ->and(is_dir(resource_path('views/shop/backend/trips')))->toBeFalse();
    });
});

describe('map picker writer', function () {
    test('a new trip starts at the departure and takes the orders in their posted order, then returns to the picker', function () {
        actingAsStoreUser(createStoreStaff('add_trip'));
        $truck = Truck::create(['name' => 'Map truck']);
        $first = mapPickerOrder();
        $second = mapPickerOrder();

        $this->post(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $second->id, 'latitude' => 99], ['order_id' => $first->id]]])
            ->assertRedirect(route('trips.create-from-map'));

        $trip = Trip::query()->sole();
        expect($trip->truck_id)->toBe($truck->id)->and($trip->driver_id)->toBeNull()->and($trip->status)->toBe('pending')
            ->and($trip->due_date->toDateString())->toBe(now()->toDateString())
            ->and($trip->checkpoints()->get(['relationable_type', 'relationable_id', 'type', 'sort_order_in_trip', 'latitudes'])->toArray())->toBe([
                ['relationable_type' => StoreUser::class, 'relationable_id' => 9, 'type' => 'other', 'sort_order_in_trip' => 1, 'latitudes' => '52.1'],
                ['relationable_type' => Order::class, 'relationable_id' => $second->id, 'type' => 'delivery', 'sort_order_in_trip' => 2, 'latitudes' => '52.1'],
                ['relationable_type' => Order::class, 'relationable_id' => $first->id, 'type' => 'delivery', 'sort_order_in_trip' => 3, 'latitudes' => '52.1'],
            ])
            ->and($truck->fresh()->is_available)->toBeFalse();
        $this->notifications->shouldHaveReceived('notifyDriver')->once();
    });

    test('appending keeps the trip, its driver and its stops and adds the orders after the last stop', function () {
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));
        $truck = Truck::create(['name' => 'Map truck']);
        $order = mapPickerOrder();
        $second = mapPickerOrder();
        $driver = createStoreUser('driver');

        $this->post(route('trips.store-from-map'), ['truck_id' => $truck->id, 'driver_id' => $driver->id, 'markers' => [['order_id' => $order->id]]])->assertRedirect(route('trips.create-from-map'));
        $trip = Trip::query()->sole();
        $original = $trip->checkpoints()->get()->map->only(['id', 'trip_id', 'latitudes', 'longitudes', 'status', 'arrived_at', 'cmr_file', 'photos'])->all();
        $this->post(route('trips.store-from-map'), ['trip_id' => $trip->id, 'truck_id' => $truck->id, 'markers' => [['order_id' => $second->id]]])->assertRedirect(route('trips.create-from-map'));

        expect(Trip::count())->toBe(1)->and($trip->fresh()->driver_id)->toBe($driver->id)
            ->and($trip->checkpoints()->pluck('relationable_id')->all())->toBe([9, $order->id, $second->id])
            ->and($trip->checkpoints()->pluck('sort_order_in_trip')->all())->toBe([1, 2, 3])
            ->and($trip->checkpoints()->whereIn('id', array_column($original, 'id'))->get()->map->only(['id', 'trip_id', 'latitudes', 'longitudes', 'status', 'arrived_at', 'cmr_file', 'photos'])->all())->toBe($original)
            ->and($truck->fresh()->is_available)->toBeFalse();
    });

    test('the writer refuses unauthorized callers without reserving a truck or creating checkpoints', function (string $caller) {
        $truck = Truck::create(['name' => 'Map truck']);
        $order = mapPickerOrder();
        actingAsRefusedStoreCaller($caller);
        $before = mapPickerState();

        assertStoreCallerRefused($this->post(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]]), $caller);

        expect(mapPickerState())->toBe($before)->and(Trip::count())->toBe(0);
    })->with('refused store admin callers');

    test('adding orders to an existing trip additionally needs edit trip', function () {
        $trip = mapPickerTrip(Truck::create(['name' => 'Busy truck', 'is_available' => false]));
        $order = mapPickerOrder();
        actingAsStoreUser(createStoreStaff('add_trip'));
        $before = mapPickerState();

        $this->post(route('trips.store-from-map'), ['trip_id' => $trip->id, 'truck_id' => $trip->truck_id, 'markers' => [['order_id' => $order->id]]])->assertForbidden();

        expect(mapPickerState())->toBe($before);
    });

    test('an invalid selection is a field error and writes nothing', function (Closure $payload, string $error) {
        $busy = Truck::create(['name' => 'Busy truck', 'is_available' => false]);
        $trip = mapPickerTrip($busy);
        $closed = mapPickerTrip($busy, ['status' => 'closed']);
        $truck = Truck::create(['name' => 'Free truck']);
        $order = mapPickerOrder();
        $pending = mapPickerOrder(['delivery_status' => 'pending']);
        $assigned = mapPickerOrder();
        $trip->checkpoints()->create(['relationable_type' => Order::class, 'relationable_id' => $assigned->id, 'type' => 'delivery', 'status' => 'pending', 'sort_order_in_trip' => 1]);
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));
        $before = mapPickerState();

        $this->from(route('trips.create-from-map'))
            ->post(route('trips.store-from-map'), $payload(compact('truck', 'busy', 'trip', 'closed', 'order', 'pending', 'assigned')))
            ->assertRedirect(route('trips.create-from-map'))->assertSessionHasErrors($error);

        expect(mapPickerState())->toBe($before);
    })->with([
        'no truck' => [fn (array $r) => ['markers' => [['order_id' => $r['order']->id]]], 'truck_id'],
        'an unknown truck' => [fn (array $r) => ['truck_id' => 999999, 'markers' => [['order_id' => $r['order']->id]]], 'truck_id'],
        'no orders' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'markers' => []], 'markers'],
        'an unknown order' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'markers' => [['order_id' => 999999]]], 'markers.0.order_id'],
        'an order not out for delivery' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'markers' => [['order_id' => $r['pending']->id]]], 'markers.0.order_id'],
        'the same order twice' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'markers' => [['order_id' => $r['order']->id], ['order_id' => $r['order']->id]]], 'markers.0.order_id'],
        'an order on an open trip' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'markers' => [['order_id' => $r['assigned']->id]]], 'markers.0.order_id'],
        'another truck\'s trip' => [fn (array $r) => ['truck_id' => $r['truck']->id, 'trip_id' => $r['trip']->id, 'markers' => [['order_id' => $r['order']->id]]], 'trip_id'],
        'a closed trip' => [fn (array $r) => ['truck_id' => $r['busy']->id, 'trip_id' => $r['closed']->id, 'markers' => [['order_id' => $r['order']->id]]], 'trip_id'],
        'an unknown trip' => [fn (array $r) => ['truck_id' => $r['busy']->id, 'trip_id' => 999999, 'markers' => [['order_id' => $r['order']->id]]], 'trip_id'],
        'a busy truck without its trip' => [fn (array $r) => ['truck_id' => $r['busy']->id, 'markers' => [['order_id' => $r['order']->id]]], 'truck_id'],
    ]);

    test('a missing departure user is a field error before any row is written', function () {
        $this->departure->delete();
        $truck = Truck::create(['name' => 'Map truck']);
        $order = mapPickerOrder();
        actingAsStoreUser(createStoreStaff('add_trip'));
        $before = mapPickerState();

        $this->from(route('trips.create-from-map'))->post(route('trips.store-from-map'), ['truck_id' => $truck->id, 'markers' => [['order_id' => $order->id]]])
            ->assertRedirect(route('trips.create-from-map'))->assertSessionHasErrors('departure');

        expect(mapPickerState())->toBe($before);
    });

    test('the writer is only a POST', function () {
        actingAsStoreUser(createStoreStaff('add_trip'));

        $this->get(route('trips.store-from-map'))->assertMethodNotAllowed();
    });
});

describe('map picker row actions', function () {
    test('a busy truck\'s trip is deleted through its DELETE and the picker reloads', function () {
        $trip = mapPickerTrip(Truck::create(['name' => 'Busy truck', 'is_available' => false]));
        actingAsStoreUser(createStoreStaff('add_trip', 'delete_trip'));

        $this->delete(route('trips.destroy', ['trip' => $trip->id, 'list' => 'map']))->assertRedirect(route('trips.create-from-map'));

        expect(Trip::find($trip->id))->toBeNull()->and($trip->truck->fresh()->is_available)->toBeTrue();
    });

    test('an order is marked delivered with return_to=map and the picker reloads', function () {
        $order = mapPickerOrder();
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip'));

        $this->put(route('trips.orders.update_order_status_to_delivered', $order), ['return_to' => 'map'])->assertRedirect(route('trips.create-from-map'));

        expect($order->fresh()->delivery_status)->toBe('delivered');
    });

    test('a missing trip or order is a 404 and changes nothing', function () {
        actingAsStoreUser(createStoreStaff('add_trip', 'edit_trip', 'delete_trip'));
        mapPickerTrip(Truck::create(['name' => 'Busy truck', 'is_available' => false]));
        mapPickerOrder();
        $before = mapPickerState();

        $this->delete(route('trips.destroy', ['trip' => 999999, 'list' => 'map']))->assertNotFound();
        $this->put(route('trips.orders.update_order_status_to_delivered', ['order' => 999999]), ['return_to' => 'map'])->assertNotFound();

        expect(mapPickerState())->toBe($before);
    });
});
