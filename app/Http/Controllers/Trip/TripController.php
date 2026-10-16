<?php

namespace App\Http\Controllers\Trip;

use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Concerns\ReservesTripTrucks;
use App\Http\Controllers\Concerns\WritesTripCheckpoints;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\StoreMapTripRequest;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\Trip;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    use ReservesTripTrucks;
    use WritesTripCheckpoints;

    protected $userService;

    protected $driverService;

    public function __construct(UserService $userService, DriverService $driverService)
    {
        $this->userService = $userService;
        $this->driverService = $driverService;
    }

    public function create_from_map()
    {
        $available_trucks = Truck::available(true)->select(['id', 'name', 'photos', 'is_available', 'max_pallets_number'])->get();
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

        $trip = DB::transaction(function () use ($data) {
            $trip = empty($data['trip_id']) ? null : Trip::query()->lockForUpdate()->findOrFail($data['trip_id']);
            $truck = $this->lockTruckForTrip((int) $data['truck_id'], $trip);
            if ($trip !== null && ($trip->truck_id !== $truck->id || $trip->status === TripStatus::Closed->value)) {
                throw ValidationException::withMessages(['trip_id' => __('validation.exists', ['attribute' => 'trip'])]);
            }
            $trip ??= $this->createTrip($data);
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
}
