<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Concerns\ReservesTripTrucks;
use App\Http\Controllers\Concerns\WritesTripCheckpoints;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\StoreMapTripRequest;
use App\Http\Resources\Store\TripCheckpointResource;
use App\Http\Resources\Store\TripMapTruckResource;
use App\Http\Resources\Store\TruckPhotoResource;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trip map picker, in place of the Blade `create-from-map` page: the
 * orders out for delivery on a Leaflet map, the free and the busy trucks,
 * and the writer that turns the selected orders into a new trip or appends
 * them to a truck's open trip.
 */
class TripMapController extends Controller implements HasMiddleware
{
    use ReservesTripTrucks;
    use WritesTripCheckpoints;

    public function __construct(private UserService $userService, private DriverService $driverService) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:add_trip')];
    }

    public function create(): Response
    {
        $orders = Order::query()->select(['id', 'user_id', 'for_customer', 'by_rep', 'code', 'shipping_address'])
            ->with(['user:id,name,phone,shipping_address,AccSysID', 'rep:id,name,phone,shipping_address,AccSysID'])
            ->where('delivery_status', 'on_delivery')
            ->orderByDesc('created_at')->orderByDesc('id')->get();
        $trucks = Truck::query()->select(['id', 'name', 'photos', 'is_available', 'max_pallets_number'])->orderBy('id')->get();
        $this->loadOpenTrips($trucks);
        $photoIds = $trucks->map(fn (Truck $truck) => TripCheckpointResource::uploadIds($truck->photos)[0] ?? null)->filter()->unique()->values()->all();

        return Inertia::render('Store/Admin/Trips/Trips/CreateFromMap', [
            'orders' => $this->getOrdersCoordinates($orders),
            'availableTrucks' => TripMapTruckResource::collection($trucks->filter(fn (Truck $truck) => $truck->is_available)->values()),
            'busyTrucks' => TripMapTruckResource::collection($trucks->reject(fn (Truck $truck) => $truck->is_available)->values()),
            'photos' => TruckPhotoResource::collection(Upload::query()
                ->select(['id', 'file_original_name', 'file_name', 'external_link', 'type', 'file_size'])
                ->where('type', 'image')->whereIn('id', $photoIds)->orderBy('id')->get()),
        ]);
    }

    /**
     * Sets each truck's `trips` to its open trips, latest first, each with its
     * driver and its `checkpoints` in stop order, every checkpoint with its
     * order (and the order's customer) or store user: four queries in all,
     * where the Blade cards queried each truck's trips several times.
     *
     * @param  Collection<int, Truck>  $trucks
     */
    private function loadOpenTrips(Collection $trucks): void
    {
        $trips = Trip::query()->select(['id', 'code', 'truck_id', 'driver_id', 'status', 'created_at'])
            ->with('driver:id,name')
            ->whereIn('truck_id', $trucks->modelKeys())
            ->where('status', '<>', TripStatus::Closed->value)
            ->orderByDesc('created_at')->orderByDesc('id')->get();
        $checkpoints = Checkpoint::query()->select(['id', 'trip_id', 'type', 'relationable_type', 'relationable_id', 'sort_order_in_trip'])
            // `with()` types its constraints as taking any relation;
            // `relationable` is always `Checkpoint`'s MorphTo.
            ->with(['relationable' => function (Relation $relation): void {
                if ($relation instanceof MorphTo) {
                    $relation->morphWith([Order::class => ['user:id,name', 'rep:id,name']]);
                    $relation->constrain([
                        StoreUser::class => fn (Builder $query) => $query->select(['id', 'name']),
                        Order::class => fn (Builder $query) => $query->select(['id', 'by_rep', 'user_id', 'for_customer']),
                    ]);
                }
            }])
            ->whereIn('trip_id', $trips->modelKeys())
            ->orderBy('sort_order_in_trip')->orderBy('id')->get()
            ->groupBy('trip_id');
        foreach ($trips as $trip) {
            $trip->setRelation('checkpoints', $checkpoints->get($trip->id, new Collection));
        }
        $trips = $trips->groupBy('truck_id');
        foreach ($trucks as $truck) {
            $truck->setRelation('trips', $trips->get($truck->id, new Collection));
        }
    }

    /**
     * The legacy writer, unchanged but for its answer: without `trip_id` a
     * new trip (departure checkpoint first, store user 9) on the truck, with
     * `trip_id` (which also needs `edit_trip`) the truck's open trip gains
     * the orders after its last stop. The truck is reserved in the same
     * transaction, the route sorted by the routing service when it answers
     * and the driver notified. Returns to the picker; the Blade writer
     * answered JSON `{success: true}`.
     */
    public function store(StoreMapTripRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! empty($data['trip_id'])) {
            Gate::authorize('edit_trip');
        }

        $trip = DB::transaction(function () use ($data) {
            $trip = empty($data['trip_id']) ? null : Trip::query()->lockForUpdate()->findOrFail((int) $data['trip_id']);
            $truck = $this->lockTruckForTrip((int) $data['truck_id'], $trip);
            if ($trip !== null && ($trip->truck_id !== $truck->id || $trip->status === TripStatus::Closed->value)) {
                throw ValidationException::withMessages(['trip_id' => __('validation.exists', ['attribute' => 'trip'])]);
            }
            $trip ??= $this->createTrip($data);
            foreach ($data['markers'] as $marker) {
                $order = Order::query()->lockForUpdate()->findOrFail((int) $marker['order_id']);
                if ($order->delivery_status !== 'on_delivery' || Checkpoint::query()->where('relationable_type', Order::class)->where('relationable_id', $order->id)->whereIn('trip_id', Trip::query()->select('id')->where('status', '<>', 'closed'))->exists()) {
                    throw ValidationException::withMessages(['markers' => __('validation.unique', ['attribute' => 'order'])]);
                }
            }
            $checkpoints = $this->prepareCheckpoints($data['markers'], empty($data['trip_id']));
            $nextOrder = (int) $trip->checkpoints()->max('sort_order_in_trip');
            foreach ($checkpoints as $checkpoint) {
                $checkpoint['sort_order_in_trip'] = ++$nextOrder;
                $trip->checkpoints()->create($checkpoint);
            }
            event(new SetTruckAvailability($trip, $truck, false));

            return $trip;
        });
        $this->sortBasedOnShortestPath($trip);
        $this->driverService->notifyDriver($trip);

        return redirect()->route('trips.create-from-map');
    }

    /**
     * The map points: every order whose customer has valid coordinates
     * (looked up as the Blade picker did), with the popup's details. The
     * address is the order's stored shipping address, else the customer's.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: int, code: string, latitude: float, longitude: float, postal_code: string|null, address: string|null, customer: array{id: int, name: string|null, phone: string|null, accounting_id: string|null}}>
     */
    private function getOrdersCoordinates(Collection $orders): array
    {
        $points = [];
        foreach ($orders as $order) {
            $customer = $order->customer;
            if ($customer === null) {
                continue;
            }
            $coordinates = $this->userService->getUserLongLat($customer, $order);
            if ($this->hasValidCoordinates($coordinates['latitude'] ?? null, $coordinates['longitude'] ?? null)) {
                $points[] = $this->formatCoordinates($coordinates, $order, $customer);
            }
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $coordinates
     * @return array{id: int, code: string, latitude: float, longitude: float, postal_code: string|null, address: string|null, customer: array{id: int, name: string|null, phone: string|null, accounting_id: string|null}}
     */
    private function formatCoordinates(array $coordinates, Order $order, StoreUser $customer): array
    {
        $address = json_decode((string) $order->shipping_address, true);
        $address = is_array($address) && $address !== [] ? $address : null;
        $postalCode = $coordinates['postal_code'] ?? null;

        return [
            'id' => $order->id,
            'code' => (string) $order->code,
            'latitude' => (float) $coordinates['latitude'],
            'longitude' => (float) $coordinates['longitude'],
            'postal_code' => $postalCode === null ? null : (string) $postalCode,
            'address' => $address === null ? $customer->getAttribute('shipping_address') : ($address['address'] ?? null),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'accounting_id' => $customer->getAttribute('AccSysID'),
            ],
        ];
    }

    /**
     * The new checkpoint rows, in the posted order: the departure checkpoint
     * first for a new trip, then each order with its stored address and the
     * coordinates of the moment.
     *
     * @param  array<int, array<string, mixed>>  $markers
     * @return array<int, array<string, mixed>>
     */
    private function prepareCheckpoints(array $markers, bool $applyDefaultCheckpoint = true): array
    {
        $checkpoints_array = [];

        if ($applyDefaultCheckpoint) {
            $this->applyDefaultFirstCheckpoint($checkpoints_array);
        }

        foreach (array_values($markers) as $key => $marker) {
            $order = Order::query()->findOrFail((int) $marker['order_id']);
            $address = json_decode($order->shipping_address);
            $long_lat_array = $this->userService->getUserLongLat($order->customer, $order);

            $checkpoint = [
                'id' => '0',
                'checkpoint_type' => 'delivery',
                'checkpoint_related_id' => $marker['order_id'],
                'checkpoint_notes' => null,
            ];

            $checkpoints_array[] = $this->formatCheckpointsArray($checkpoint, $address, $key + 1, Order::class, $long_lat_array);
        }

        return $checkpoints_array;
    }
}
