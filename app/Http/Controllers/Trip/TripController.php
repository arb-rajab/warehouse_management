<?php

namespace App\Http\Controllers\Trip;

use App\Enums\CheckpointStatus;
use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\StoreMapTripRequest;
use App\Http\Requests\Store\Admin\StoreTripRequest;
use App\Http\Requests\Store\Admin\UpdateCheckpointStatusRequest;
use App\Http\Requests\Store\Admin\UpdateTripRequest;
use App\Http\Requests\Store\Admin\UpdateTripStatusRequest;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    protected $userService;

    protected $driverService;

    public function __construct(UserService $userService, DriverService $driverService)
    {
        $this->userService = $userService;
        $this->driverService = $driverService;
    }

    public function getTodayTrips(Request $request)
    {
        return $this->getTrips($request, 'today');
    }

    public function getAllTrips(Request $request)
    {
        return $this->getTrips($request, 'all');
    }

    public function show($id)
    {
        $trip = Trip::findOrFail($id);

        $checkpoints = $trip->checkpoints()->select(['id', 'trip_id', 'relationable_type', 'relationable_id', 'type', 'status', 'arrived_at', 'longitudes', 'latitudes', 'sort_order_in_trip', 'eta', 'notes', 'cmr_file', 'photos'])->with(['relationable' => function (MorphTo $relation): void {
            $relation->morphWith([Order::class => ['user:id,name,phone,company_name,shipping_address', 'rep:id,name,phone,company_name,shipping_address', 'orderDetails:id,order_id']]);
            $relation->constrain([
                StoreUser::class => fn ($query) => $query->select(['id', 'name', 'phone', 'company_name', 'shipping_address']),
                Order::class => fn ($query) => $query->select(['id', 'by_rep', 'for_customer', 'user_id', 'code', 'grand_total', 'invoice_number']),
            ]);
        }])->orderBy('sort_order_in_trip')->get();

        $map_array = $this->getCheckpointsMapData($checkpoints);
        $coordinates = $map_array['coordinates'];
        $long_lat_array = $map_array['long_lat_array'];

        return view('shop.backend.trips.checkpoints.index', compact('trip', 'checkpoints', 'coordinates', 'long_lat_array'));

    }

    public function create()
    {
        $trip = null; // to fix frontend issue

        return view('shop.backend.trips.create', compact('trip'));
    }

    public function create_from_map()
    {
        $available_trucks = Truck::available(true)->select(['id', 'name', 'photos', 'is_available'])->get();
        $unavailable_trucks = Truck::available(false)->select(['id', 'name', 'photos', 'is_available'])->get();
        $trip = null; // to fix frontend issue

        $orders = Order::query()->select(['id', 'user_id', 'for_customer', 'by_rep', 'code', 'shipping_address'])->with(['user:id,name,phone,shipping_address,AccSysID', 'rep:id,name,phone,shipping_address,AccSysID'])->where('delivery_status', 'on_delivery')->orderBy('created_at', 'desc')->get();
        $long_lat_array = $this->getOrdersCoordinates($orders);

        return view('shop.backend.trips.create-from-map', compact('trip', 'available_trucks', 'unavailable_trucks', 'long_lat_array'));
    }

    public function store_from_map(StoreMapTripRequest $request)
    {
        $data = $request->validated();
        if (! empty($data['trip_id'])) {
            Gate::authorize('edit_trip');
        }

        $trip = DB::transaction(function () use ($data, $request) {
            $trip = empty($data['trip_id']) ? null : Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);
            $truck = $this->lockTruckForTrip((int) $data['truck_id'], $trip);
            if ($trip !== null && ($trip->truck_id !== $truck->id || $trip->status === TripStatus::Closed->value)) {
                throw ValidationException::withMessages(['trip_id' => __('validation.exists', ['attribute' => 'trip'])]);
            }
            $trip ??= $this->createTrip($request);
            foreach ($data['markers'] as $marker) {
                $order = Order::query()->lockForUpdate()->findOrFail($marker['order_id']);
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

        return response()->json(['success' => true]);
    }

    public function store(StoreTripRequest $request)
    {
        $data = $request->validated();
        $trip = DB::transaction(function () use ($data, $request) {
            $truck = $this->lockTruckForTrip((int) $data['truck_id']);
            $checkpoints = [];
            $this->applyDefaultFirstCheckpoint($checkpoints);
            $checkpoints = [...$checkpoints, ...$this->prepareCheckpointRows($data['checkpoints_array'], offset: 1)];
            $trip = $this->createTrip($request);
            $trip->checkpoints()->createMany($checkpoints);
            event(new SetTruckAvailability($trip, $truck, false));

            return $trip;
        });
        $this->sortBasedOnShortestPath($trip);
        $this->driverService->notifyDriver($trip);

        return redirect()->route('trips.show', $trip->id);
    }

    public function edit($id)
    {
        $trip = Trip::findOrFail($id);

        return view('shop.backend.trips.edit', compact('trip'));
    }

    public function update(UpdateTripRequest $request)
    {
        $data = $request->validated();
        $driverChanged = false;
        $trip = DB::transaction(function () use ($data, &$driverChanged) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($data['id']);
            $oldTruckId = $trip->truck_id;
            $truck = $this->lockTruckForTrip((int) $data['truck_id'], $trip, reserve: $trip->status !== TripStatus::Closed->value);
            $driverChanged = $trip->driver_id !== ($data['driver_id'] ?? null);
            $checkpoints = $this->prepareCheckpointRows($data['checkpoints_array'], $trip);
            $trip->fill([
                'driver_id' => $data['driver_id'] ?? null,
                'truck_id' => $truck->id,
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
            ])->save();
            $retained = array_filter(array_column($checkpoints, 'id'));
            $trip->checkpoints()->whereNotIn('id', $retained)->delete();
            foreach ($checkpoints as $checkpoint) {
                $id = $checkpoint['id'] ?? null;
                unset($checkpoint['id']);
                if ($id !== null) {
                    $trip->checkpoints()->whereKey($id)->update($checkpoint);
                } else {
                    $trip->checkpoints()->create($checkpoint);
                }
            }
            $available = $trip->status === TripStatus::Closed->value && ! $truck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
            event(new SetTruckAvailability($trip, $truck, $available));
            if ($oldTruckId !== null && $oldTruckId !== $truck->id) {
                $oldTruck = Truck::query()->lockForUpdate()->find($oldTruckId);
                if ($oldTruck !== null) {
                    $available = ! $oldTruck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
                    event(new SetTruckAvailability($trip, $oldTruck, $available));
                }
            }

            return $trip;
        });
        $this->sortBasedOnShortestPath($trip);
        if ($driverChanged) {
            $this->driverService->notifyDriver($trip);
        }

        return redirect()->route('trips.show', $trip->id);
    }

    protected function lockTruckForTrip(int $truckId, ?Trip $trip = null, bool $reserve = true): Truck
    {
        $truck = Truck::query()->lockForUpdate()->findOrFail($truckId);
        if (! $reserve) {
            return $truck;
        }
        $otherTrip = $truck->trips()->where('status', '<>', TripStatus::Closed->value);
        if ($trip !== null) {
            $otherTrip->whereKeyNot($trip->id);
        }
        if ($otherTrip->exists() || (! $truck->is_available && ($trip === null || $trip->truck_id !== $truck->id))) {
            throw ValidationException::withMessages(['truck_id' => __('validation.exists', ['attribute' => 'available truck'])]);
        }

        return $truck;
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function prepareCheckpointRows(array $rows, ?Trip $trip = null, int $offset = 0): array
    {
        $checkpoints = [];
        foreach (array_values($rows) as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $checkpoint = $trip?->checkpoints()->lockForUpdate()->find($id);
                $type = $row['checkpoint_type'] === 'delivery' ? Order::class : StoreUser::class;
                if ($checkpoint === null || $checkpoint->relationable_type !== $type || $checkpoint->relationable_id !== (int) $row['checkpoint_related_id'] || $checkpoint->type !== $row['checkpoint_type']) {
                    throw ValidationException::withMessages(['checkpoints_array' => __('validation.exists', ['attribute' => 'checkpoint'])]);
                }
                $checkpoints[] = ['id' => $id, 'sort_order_in_trip' => $index + $offset + 1, 'notes' => $row['checkpoint_notes'] ?? ''];

                continue;
            }
            if ($row['checkpoint_type'] === 'delivery') {
                $order = Order::query()->findOrFail($row['checkpoint_related_id']);
                $address = json_decode($order->shipping_address);
                $coordinates = $this->userService->getUserLongLat($order->customer, $order);
                $type = Order::class;
            } else {
                $customer = StoreUser::query()->findOrFail($row['checkpoint_related_id']);
                $address = formateAddressArray($customer->addresses->where('set_default', true)->first());
                $coordinates = $this->userService->getUserLongLat($customer);
                $type = StoreUser::class;
            }
            $checkpoints[] = $this->formatCheckpointsArray($row, $address, $index + $offset, $type, $coordinates);
        }

        return $checkpoints;
    }

    public function delete($id)
    {
        $trip = Trip::find($id);
        if ($trip) {
            // Delete related checkpoints
            $trip->checkpoints()->delete();

            event(new SetTruckAvailability($trip, $trip->truck, true));

            $trip->delete();
            flash('Trip Deleted Successfully')->success();
        } else {
            flash('Error: trip Not Found')->error();
        }

        return redirect()->back();
    }

    public function bulk_trip_delete(Request $request)
    {
        $deletedCount = 0;

        try {
            foreach ($request->id as $trip_id) {
                $trip = Trip::find($trip_id);
                if ($trip) {

                    $trip->checkpoints()->delete();

                    $trip->delete();
                    $deletedCount++;
                }
            }
        } catch (\Exception $e) {
            flash('An error occurred during bulk delete')->error();

            return 0;
        }

        return 1;
    }

    public function update_trip_notes(Request $request)
    {
        $trip = Trip::find($request->trip_id);

        if (! $trip) {
            return response()->json(['success' => false]);
        }

        $trip->notes = $request->notes;

        if ($trip->save()) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }

    public function update_trip_status(UpdateTripStatusRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($data['id']);
            $status = TripStatus::from($data['status']);
            $truck = $status === TripStatus::Closed
                ? Truck::query()->lockForUpdate()->findOrFail($trip->truck_id)
                : $this->lockTruckForTrip((int) $trip->truck_id, $trip);
            $trip->status = $status->value;
            $trip->active = $status !== TripStatus::Closed;
            $trip->closed_at = $status === TripStatus::Closed ? ($trip->closed_at ?? now()) : null;
            if ($status === TripStatus::Started) {
                $trip->started_at ??= now();
            }
            $trip->save();
            if ($status === TripStatus::Closed) {
                foreach ($trip->checkpoints()->select('relationable_id')->where('relationable_type', Order::class)->get() as $checkpoint) {
                    Order::query()->whereKey($checkpoint->relationable_id)->update(['delivery_status' => 'delivered']);
                }
            }
            $available = $status === TripStatus::Closed && ! $truck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
            event(new SetTruckAvailability($trip, $truck, $available));
        });

        return 1;
    }

    public function update_checkpoint_notes(Request $request)
    {
        $checkpoint = Checkpoint::find($request->checkpoint_id);

        if (! $checkpoint) {
            return response()->json(['success' => false]);
        }

        $checkpoint->notes = $request->notes;

        if ($checkpoint->save()) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }

    public function update_checkpoint_status(UpdateCheckpointStatusRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $checkpoint = Checkpoint::query()->lockForUpdate()->findOrFail($data['id']);
            $checkpoint->status = $data['status'];
            if ($data['status'] === CheckpointStatus::Delivered->value) {
                $checkpoint->arrived_at ??= now();
            }
            $checkpoint->save();
        });

        return 1;
    }

    public function print_cmr($id)
    {
        $checkpoint = Checkpoint::find($id);

        if ($checkpoint && $checkpoint->cmr_file) {
            $filePath = uploaded_asset($checkpoint->cmr_file);

            return redirect()->to(url($filePath));
        }

        return redirect()->back()->with('error', 'File not found on the server');
    }

    protected function getTrips(Request $request, $type = 'all')
    {
        $trips = Trip::query()->select(['id', 'code', 'driver_id', 'truck_id', 'due_date', 'status', 'started_at', 'closed_at', 'distance', 'duration', 'notes'])->with(['driver:id,name', 'truck:id,name']);

        if ($type === 'today') {
            $trips->where('due_date', today());
        }

        // Handle search functionality
        $sort_search = $request->input('search');
        if ($sort_search) {
            $trips->search($sort_search);
        }

        $this->applyStatusFilters($request, $trips);

        $trips = $trips->orderBy('created_at', 'desc')->paginate(15);

        $page_title = $type === 'today' ? translate('Today Trips') : translate('All Trips');

        return view('shop.backend.trips.index', compact('trips', 'sort_search', 'page_title'));
    }

    protected function getOrdersCoordinates($orders)
    {
        $long_lat_array = [];

        foreach ($orders as $order) {
            if ($order->customer) {
                $coordinates = $this->userService->getUserLongLat($order->customer, $order);

                if ($this->hasValidCoordinates($coordinates['latitude'] ?? null, $coordinates['longitude'] ?? null)) {
                    $long_lat_array[] = $this->formatCoordinates($coordinates, $order);
                }
            }
        }

        return $long_lat_array;
    }

    protected function formatCoordinates($coordinates, $order)
    {
        return [
            'latitude' => (string) $coordinates['latitude'],
            'longitude' => (string) $coordinates['longitude'],
            'label' => $order->customer->name,
            'user_id' => $order->customer->id,
            'order_id' => $order->id,
            'order_code' => $order->code,
            'postal_code' => $coordinates['postal_code'],
            'shipping_address' => $order->shipping_address,
            'phone' => $order->customer->phone,
            'otajer_id' => $order->customer->AccSysID,
        ];
    }

    protected function prepareCheckpoints($markers, $applyDefaultCheckpoint = true)
    {
        $checkpoints_array = [];

        if ($applyDefaultCheckpoint) {
            $this->applyDefaultFirstCheckpoint($checkpoints_array);
        }

        foreach ($markers as $key => $marker) {
            $order = Order::find($marker['order_id']);
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

    protected function createTrip($request)
    {
        $trip = Trip::create([
            'driver_id' => $request->driver_id ?? null,
            'truck_id' => $request->truck_id,
            'due_date' => $request->due_date ?? now(),
            'notes' => $request->notes,
            'code' => now()->format('Ymd-His'),
            'active' => true,
        ]);

        return $trip;
    }

    protected function formatCheckpointsArray($checkpoint, $address, $key, $relationable_type, $long_lat_array = [])
    {
        return [
            'type' => $checkpoint['checkpoint_type'],
            'sort_order_in_trip' => $key + 1,
            'longitudes' => $long_lat_array['longitude'] ?? null,
            'latitudes' => $long_lat_array['latitude'] ?? null,
            'address' => $address,
            'relationable_id' => $checkpoint['checkpoint_related_id'],
            'relationable_type' => $relationable_type,
            'notes' => $checkpoint['checkpoint_notes'] ?? '',
        ];
    }

    protected function getCheckpointsMapData($checkpoints)
    {
        $points = $this->checkpointMapPoints($checkpoints);
        $coordinates = [];
        if (count($points) < 2) {
            return ['long_lat_array' => $points, 'coordinates' => $coordinates];
        }
        try {
            $coordinateString = implode(';', array_map(fn ($point) => "{$point['longitude']},{$point['latitude']}", $points));
            $response = Http::connectTimeout(2)->timeout(5)->get("https://router.project-osrm.org/route/v1/driving/{$coordinateString}", [
                'overview' => 'full',
                'geometries' => 'geojson',
            ])->throw();
            $coordinates = $response->json('routes.0.geometry.coordinates', []);
        } catch (\Exception $exception) {
            // Keep the independently useful markers when routing is unavailable.
        }

        return ['long_lat_array' => $points, 'coordinates' => $coordinates];
    }

    protected function checkpointMapPoints($checkpoints): array
    {
        return $checkpoints->filter(fn ($checkpoint) => $this->hasValidCoordinates($checkpoint->latitudes, $checkpoint->longitudes))
            ->map(fn ($checkpoint) => [
                'longitude' => $checkpoint->longitudes,
                'latitude' => $checkpoint->latitudes,
                'label' => $checkpoint->sort_order_in_trip.' | '.($checkpoint->getCustomerForCheckpoint()?->name ?? '-'),
            ])->values()->all();
    }

    protected function hasValidCoordinates($latitude, $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && (float) $latitude >= -90 && (float) $latitude <= 90
            && (float) $longitude >= -180 && (float) $longitude <= 180;
    }

    protected function sortBasedOnShortestPath($trip)
    {
        $checkpoints = $trip->checkpoints()->select(['id', 'trip_id', 'longitudes', 'latitudes', 'sort_order_in_trip', 'eta'])->orderBy('sort_order_in_trip')->get();
        $validCheckpoints = $checkpoints->filter(fn ($checkpoint) => $this->hasValidCoordinates($checkpoint->latitudes, $checkpoint->longitudes))->values();
        if ($validCheckpoints->count() < 2) {
            return;
        }
        $coordinateString = $validCheckpoints->map(fn ($checkpoint) => "{$checkpoint->longitudes},{$checkpoint->latitudes}")->implode(';');
        try {
            $data = Http::connectTimeout(2)->timeout(5)->get("https://router.project-osrm.org/trip/v1/driving/{$coordinateString}", [
                'overview' => 'full',
                'geometries' => 'geojson',
            ])->throw()->json();
            if (count($data['waypoints'] ?? []) !== $validCheckpoints->count() || ! isset($data['trips'][0]['duration'], $data['trips'][0]['distance'])) {
                return;
            }
            $positions = $validCheckpoints->pluck('sort_order_in_trip')->sort()->values();
            DB::transaction(function () use ($trip, $data, $positions, $validCheckpoints): void {
                foreach ($validCheckpoints as $index => $checkpoint) {
                    $order = $data['waypoints'][$index]['waypoint_index'];
                    if (! isset($positions[$order])) {
                        throw new \UnexpectedValueException('Invalid route waypoint index.');
                    }
                    $checkpoint->update([
                        'sort_order_in_trip' => $positions[$order],
                        'eta' => $data['trips'][0]['legs'][$order]['duration'] ?? null,
                    ]);
                }
                $trip->update(['duration' => $data['trips'][0]['duration'], 'distance' => $data['trips'][0]['distance']]);
            });
        } catch (\Exception $exception) {
            // A routing outage must not prevent creating or editing a trip.
        }
    }

    /**
     * Apply status filters to the query based on the request.
     *
     * @param  Builder  $query
     */
    protected function applyStatusFilters(Request $request, $query)
    {
        $pending = $request->has('pending');
        $started = $request->has('started');
        $closed = $request->has('closed');
        $allTrips = $request->has('allTrips');

        if (! $allTrips && ($pending || $started || $closed)) {
            $query->where(function ($subQuery) use ($pending, $started, $closed) {
                if ($pending) {
                    $subQuery->orWhere('status', TripStatus::Pending->value);
                }
                if ($started) {
                    $subQuery->orWhere('status', TripStatus::Started->value);
                }
                if ($closed) {
                    $subQuery->orWhere('status', TripStatus::Closed->value);
                }
            });
        }
    }

    protected function applyDefaultFirstCheckpoint(&$checkpoints_array)
    {
        $default_starting_checkpoint = [
            'id' => '0',
            'checkpoint_type' => 'other',
            'checkpoint_related_id' => 9,
            'checkpoint_notes' => __('Default starting checkpoint'),
        ];

        $default_starting_checkpoint_user = StoreUser::find(9);
        if ($default_starting_checkpoint_user === null) {
            throw ValidationException::withMessages(['departure' => __('messages.store_trip_departure_unavailable')]);
        }
        $long_lat_array = $this->userService->getUserLongLat($default_starting_checkpoint_user);
        $checkpoints_array[] = $this->formatCheckpointsArray($default_starting_checkpoint, [], 0, StoreUser::class, $long_lat_array);
    }

    public function all_orders(Request $request)
    {
        $date = $request->date;
        $sort_search = null;
        $delivery_status = null;
        $payment_status = '';

        // Retrieve the cached delivery_status from the session
        if ($request->has('delivery_status')) {
            // If a new delivery_status is provided, update the session
            $delivery_status = $request->delivery_status;
            session(['delivery_status' => $delivery_status]); // Cache in session
        } else {
            // If no delivery_status is provided, use the cached value from the session
            $delivery_status = session('delivery_status');
        }

        $orders = Order::whereNotIn('delivery_status', ['pending', 'cancelled'])->orderBy('created_at', 'desc');
        $admin_user_id = StoreUser::where('user_type', 'admin')->first()->id;

        if ($request->search) {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%'.$sort_search.'%')
                ->orWhere('invoice_number', 'like', '%'.$sort_search.'%')
                ->orWhere('shipping_address', 'like', '%'.$sort_search.'%')
                ->orWhereHas('customer', function ($query) use ($sort_search) {
                    $query->where('name', 'like', '%'.$sort_search.'%')
                        ->orWhere('phone', 'like', '%'.$sort_search.'%')
                        ->orWhere('AccSysID', 'like', '%'.$sort_search.'%');
                })
                ->orWhereHas('rep', function ($query) use ($sort_search) {
                    $query->where('name', 'like', '%'.$sort_search.'%')
                        ->orWhere('AccSysID', 'like', '%'.$sort_search.'%');
                });
        }

        if ($delivery_status != null) {
            $orders = $orders->where('delivery_status', $delivery_status);
        }

        if ($date != null) {
            $orders = $orders->where('created_at', '>=', date('Y-m-d', strtotime(explode(' to ', $date)[0])).'  00:00:00')
                ->where('created_at', '<=', date('Y-m-d', strtotime(explode(' to ', $date)[1])).'  23:59:59');
        }

        $orders = $orders->paginate(15);

        return view('shop.backend.trips.orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
    }

    public function bulk_change_delivery_status(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $order_id) {
                $order = Order::findOrFail($order_id);
                if ($request->delivery_status == 'confirmed') {
                    $order->delivery_status = 'confirmed';
                } elseif ($request->delivery_status == 'ready_for_delivery') {
                    $order->delivery_status = 'ready_for_delivery';
                }
                $order->save();
            }
        }

        return 1;
    }

    public function updateInvoiceNumber(Request $request)
    {
        $order = Order::find($request->order_id);
        if ($order) {
            $order->invoice_number = $request->invoice_number;
            $order->delivery_status = 'on_delivery';
            $order->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }

    public function updateOrderStatusToDelivered(Request $request)
    {
        $order = Order::find($request->order_id);
        if ($order) {
            $order->delivery_status = 'delivered';
            $order->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }
}
