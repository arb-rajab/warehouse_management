<?php

namespace App\Http\Controllers\Trip;

use App\Enums\CheckpointStatus;
use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Concerns\ReservesTripTrucks;
use App\Http\Controllers\Concerns\WritesTripCheckpoints;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\StoreMapTripRequest;
use App\Http\Requests\Store\Admin\UpdateCheckpointStatusRequest;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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
