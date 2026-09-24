<?php

namespace App\Http\Controllers\Trip;

use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\TripRequest;
use App\Models\Checkpoint;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use App\Services\DriverService;
use App\Services\UserService;

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

        $checkpoints = $trip->checkpoints()->orderBy('sort_order_in_trip')->get();

        $map_array = $this->getCheckpointsMapData($checkpoints);
        $coordinates = $map_array['coordinates'];
        $long_lat_array = $map_array['long_lat_array'];

        return view('backend.trips.checkpoints.index', compact('checkpoints', 'coordinates', 'long_lat_array'));

    }

    public function create()
    {
        $trip = null; // to fix frontend issue
        return view('backend.trips.create', compact('trip'));
    }

    public function create_from_map()
    {
        $available_trucks = Truck::available(true)->get();
        $unavailable_trucks = Truck::available(false)->get();
        $trip = null; // to fix frontend issue
        
        $orders = Order::where('delivery_status', 'on_delivery')->orderBy('created_at', 'desc')->get();
        $long_lat_array = $this->getOrdersCoordinates($orders);

        return view('backend.trips.create-from-map', compact('trip', 'available_trucks', 'unavailable_trucks', 'long_lat_array'));
    }

    public function store_from_map(Request $request)
    {
        if ($request->trip_id) {
            // Add markers to existing trip
            $checkpoints_array = $this->prepareCheckpoints($request->markers, false);
            $trip = Trip::find($request->trip_id);
            $trip->checkpoints()->createMany($checkpoints_array);
        } else {
            // Create a new trip
            $checkpoints_array = $this->prepareCheckpoints($request->markers, true);
            $trip = $this->createTrip($request);
            $trip->checkpoints()->createMany($checkpoints_array);
        }

        $this->sortBasedOnShortestPath($trip);
        $this->driverService->notifyDriver($trip);

        $truck = Truck::find($request->truck_id);
        event(new SetTruckAvailability($trip, $truck, false));

        flash('Trip Created Successfully')->success();
        return response()->json(['success' => true]);
    }

    public function store(TripRequest $request)
    {
        try {
            $checkpoints_array = [];

            $this->applyDefaultFirstCheckpoint($checkpoints_array);

            foreach($request->checkpoints_array as $key => $checkpoint) {
                if($checkpoint['checkpoint_type'] == 'delivery'){
                    $order = Order::find($checkpoint['checkpoint_related_id']);
                    $address = json_decode($order->shipping_address);

                    $long_lat_array = $this->userService->getUserLongLat($order->customer, $order);

                    // key increased by one because we added the default checkpoint as the first checkpoint
                    $checkpoints_array[] = $this->formatCheckpointsArray($checkpoint, $address , $key + 1, Order::class,  $long_lat_array);

                } elseif($checkpoint['checkpoint_type'] == 'other'){
                    $customer = User::find($checkpoint['checkpoint_related_id']);
                    $customer_address = $customer->addresses->where('set_default', true)->first();

                    $formatted_Address = formateAddressArray($customer_address);
                    $long_lat_array = $this->userService->getUserLongLat($customer);

                    // key increased by one because we added the default checkpoint as the first checkpoint
                    $checkpoints_array[] = $this->formatCheckpointsArray($checkpoint, $formatted_Address , $key + 1, User::class, $long_lat_array);
                }
            }

            $trip = Trip::create([
                'driver_id' => $request->driver_id ?? null,
                'truck_id' => $request->truck_id,
                'due_date' => $request->due_date,
                'notes' => $request->notes,
            ]);

            $trip->code = date('Ymd-His');
            $trip->save();

            $trip->checkpoints()->createMany($checkpoints_array);

            if(true){ // $request->sort_by_shortest is true for now
                $this->sortBasedOnShortestPath($trip);
            }

            $this->driverService->notifyDriver($trip);

            $truck = Truck::find($trip->truck_id);
            event(new SetTruckAvailability($trip, $truck, false));

        } catch (\Exception $e){
            flash('Error, ' . $e)->error();
            return redirect()->back()->withInput();
        }

        flash('Trip Created Successfully')->success();
        return redirect()->route('trips.show', $trip->id);
    }

    public function edit($id)
    {
        $trip = Trip::find($id);

        return view('backend.trips.edit', compact('trip'));
    }

    public function update(TripRequest $request)
    {
        try {
            $trip = Trip::findOrFail($request->id);
            $old_driver_id = $trip->driver_id ?? null;

            $trip->driver_id = $request->driver_id ?? null;
            $trip->truck_id = $request->truck_id;
            $trip->due_date = $request->due_date;
            $trip->notes = $request->notes;
            $trip->save();

            // Prepare the checkpoints to be synced
            $checkpoints_array = [];
            foreach($request->checkpoints_array as $key => $checkpoint) {
                if($checkpoint['checkpoint_type'] == 'delivery'){
                    $order = Order::find($checkpoint['checkpoint_related_id']);
                    $address = json_decode($order->shipping_address);

                    $checkpoints_array[] = $this->formatCheckpointsArray($checkpoint, $address , $key, Order::class);

                } elseif($checkpoint['checkpoint_type'] == 'other'){
                    $customer = User::find($checkpoint['checkpoint_related_id']);
                    $customer_address = $customer->addresses->where('set_default', true)->first();

                    $formatted_Address = formateAddressArray($customer_address);

                    $checkpoints_array[] = $this->formatCheckpointsArray($checkpoint, $formatted_Address , $key, User::class);
                }
            }

            // Get the ids of the checkpoints in the array
            $checkpointIds = collect($checkpoints_array)->pluck('id')->toArray();

            // Sync the checkpoints: it will update existing ones and delete the ones not in the array
            $trip->checkpoints()->whereNotIn('id', $checkpointIds)->delete();

            // Sync the checkpoints with the trip
            foreach ($checkpoints_array as $checkpoint) {
                $trip->checkpoints()->updateOrCreate(
                    [
                        'id' => $checkpoint['id'],
                    ],
                        $checkpoint
                    );
            }

            $this->sortBasedOnShortestPath($trip);

            if($old_driver_id != $request->driver_id){
                $this->driverService->notifyDriver($trip);
            }

            $truck = Truck::find($trip->truck_id);
            event(new SetTruckAvailability($trip, $truck, false));

        } catch (\Exception $e) {
            flash('Error updating trip')->error();
            return redirect()->back()->withInput();
        }

        flash('Trip updated successfully')->success();
        return redirect()->route('trips.show', $trip->id);
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

        if (!$trip) {
            return response()->json(['success' => false]);
        }

        $trip->notes = $request->notes;

        if ($trip->save()) {
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    public function update_trip_status(Request $request)
    {
        $trip = Trip::find($request->id);

        if (!$trip) {
            return 0;
        }

        $trip->status = $request->status;

        $truck = Truck::find($trip->truck_id);
        switch ($request->status) {
            case \App\Enums\TripStatus::Started->value:
                $trip->started_at = now();
                event(new SetTruckAvailability($trip, $truck, false));
                break;

            case \App\Enums\TripStatus::Closed->value:
                $trip->closed_at = now();
                $trip->active = false;
                foreach($trip->checkpoints as $checkpoint){
                    if($checkpoint->order){
                        $checkpoint->order->delivery_status = 'delivered';
                        $checkpoint->order->save();
                    }
                }
                event(new SetTruckAvailability($trip, $truck, true));
                break;

            default:
                event(new SetTruckAvailability($trip, $truck, false));
                break; // No date update for 'pending'
        }

        if ($trip->save()) {
            return 1;
        }
        return 0;
    }

    public function update_checkpoint_notes(Request $request)
    {
        $checkpoint = Checkpoint::find($request->checkpoint_id);

        if (!$checkpoint) {
            return response()->json(['success' => false]);
        }

        $checkpoint->notes = $request->notes;

        if ($checkpoint->save()) {
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    public function update_checkpoint_status(Request $request)
    {
        $checkpoint = Checkpoint::find($request->id);

        if (!$checkpoint) {
            return 0;
        }

        $checkpoint->status = $request->status;

        // Update corresponding date based on status
        if(\App\Enums\CheckpointStatus::Delivered->value){
            $checkpoint->arrived_at = now();
        }

        if ($checkpoint->save()) {
            return 1;
        }

        return 0;
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
        $trips = Trip::query();

        if ($type === 'today') {
            $trips->where('due_date', today());
        }

        // Handle search functionality
        $sort_search = $request->input('search');
        if ($sort_search) {
            $trips->with(['driver', 'truck', 'checkpoints.relationable'])
                ->search($sort_search);
        }

        $this->applyStatusFilters($request, $trips);

        $trips = $trips->orderBy('created_at', 'desc')->paginate(15);

        $page_title = $type === 'today' ? translate('Today Trips') : translate('All Trips');

        return view('backend.trips.index', compact('trips', 'sort_search', 'page_title'));
    }

    protected function getOrdersCoordinates($orders)
    {
        $long_lat_array = [];

        foreach ($orders as $order) {
            if ($order->customer) {
                $coordinates = $this->userService->getUserLongLat($order->customer, $order);

                if (isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
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
            'shipping_address' => $order->customer->shipping_address,
            'phone' => $order->customer->phone,
            'otajer_id' => $order->customer->AccSysID,
        ];
    }

    protected function prepareCheckpoints($markers, $applyDefaultCheckpoint = true)
    {
        $checkpoints_array = [];

        if($applyDefaultCheckpoint){
            $this->applyDefaultFirstCheckpoint($checkpoints_array);
        }

        foreach ($markers as $key => $marker) {
            $order = Order::find($marker['order_id']);
            $address = json_decode($order->shipping_address);
            $long_lat_array = $this->userService->getUserLongLat($order->customer, $order);

            $checkpoint = [
                "id" => "0",
                "checkpoint_type" => "delivery",
                "checkpoint_related_id" => $marker['order_id'],
                "checkpoint_notes" => null
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
        ]);

        $trip->code = date('Ymd-His');
        $trip->save();

        return $trip;
    }

    protected function formatCheckpointsArray($checkpoint, $address, $key, $relationable_type, $long_lat_array = [])
    {
        return [
            'id' => $checkpoint['id'],
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
        try{
            $long_lat_array = $checkpoints->map(function ($checkpoint) {
                return [
                    'longitude' => $checkpoint->longitudes ?? null,
                    'latitude' => $checkpoint->latitudes ?? null,
                    'label' => $checkpoint->sort_order_in_trip . ' | ' . $checkpoint->getCustomerForCheckpoint()?->name ?? '-'
                ];
            })

            // Filter out invalid points (missing longitude or latitude)
            ->filter(function ($point) {
                return isset($point['longitude'], $point['latitude'])
                    && is_numeric($point['longitude'])
                    && is_numeric($point['latitude']);
            })
            ->toArray();


            // Convert the array of coordinates to OSRM format: "lon1,lat1;lon2,lat2;..."
            $coordinateString = implode(';', array_map(function ($point) {
                return "{$point['longitude']},{$point['latitude']}";
            }, $long_lat_array));

            $client = new \GuzzleHttp\Client();
            $url = "http://router.project-osrm.org/route/v1/driving/{$coordinateString}";

            $response = $client->get($url, [
                'query' => [
                    'overview' => 'full', // or 'simplified' for less detailed routes
                    'geometries' => 'geojson'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            $route = $data['routes'][0] ?? null;
            $coordinates = $route['geometry']['coordinates'] ?? [];

            return [
                'long_lat_array' => $long_lat_array,
                'coordinates' => $coordinates,
            ];

        } catch (\Exception $e){
            return [
                'long_lat_array' => [],
                'coordinates' => [],
            ];
        }
    }

    protected function sortBasedOnShortestPath($trip)
    {
        try{
            $checkpoints = $trip->checkpoints()->orderBy('sort_order_in_trip')->get();

            $long_lat_array = $checkpoints->map(function ($checkpoint) {
                return [
                    'longitude' => $checkpoint->longitudes ?? null,
                    'latitude' => $checkpoint->latitudes ?? null,
                    'label' => $checkpoint->sort_order_in_trip . ' | ' . $checkpoint->getCustomerForCheckpoint()?->name ?? '-'
                ];
            })
            // Filter out invalid points (missing longitude or latitude)
            ->filter(function ($point) {
                return isset($point['longitude'], $point['latitude'])
                    && is_numeric($point['longitude'])
                    && is_numeric($point['latitude']);
            })
            ->toArray();


            // Convert the array of coordinates to OSRM format: "lon1,lat1;lon2,lat2;..."
            $coordinateString = implode(';', array_map(function ($point) {
                return "{$point['longitude']},{$point['latitude']}";
            }, $long_lat_array));

            // Step 2: Send request to OSRM `/trip` endpoint
            $client = new \GuzzleHttp\Client();
            $url = "http://router.project-osrm.org/trip/v1/driving/{$coordinateString}";

            $response = $client->get($url, [
                'query' => [
                    'overview' => 'full', // or 'simplified' for less detailed routes
                    'geometries' => 'geojson'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            // Step 3: Map OSRM response to update `sort_order_in_trip`
            if (!empty($data['waypoints'])) {
                $waypointOrder = array_map(function ($waypoint) {
                    return [
                        'index' => $waypoint['waypoint_index'],
                        'longitude' => $waypoint['location'][0],
                        'latitude' => $waypoint['location'][1],
                    ];
                }, $data['waypoints']);

                // Update sort_order_in_trip
                foreach ($checkpoints as &$checkpoint) {
                    foreach ($waypointOrder as $order) {
                        // Convert to strings
                        $longitude = (string)$checkpoint['longitudes'];
                        $latitude = (string)$checkpoint['latitudes'];

                        // Handle negative values and extract the first three significant digits
                        $longFirstThree = substr($longitude, 0, ($longitude[0] === '-') ? 4 : 3);
                        $latFirstThree = substr($latitude, 0, ($latitude[0] === '-') ? 4 : 3);

                        // Handle negative values and extract the first three significant digits
                        $longFirstThree2 = substr($order['longitude'], 0, ($longitude[0] === '-') ? 4 : 3);
                        $latFirstThree2 = substr($order['latitude'], 0, ($latitude[0] === '-') ? 4 : 3);

                        if (
                            (float )$longFirstThree == (float) $longFirstThree2 &&
                            (float)$latFirstThree == (float)$latFirstThree2
                        ) {

                            $checkpoint['sort_order_in_trip'] = $order['index'] + 1; // Make index 1-based
                            $checkpoint['eta'] = $data['trips'][0]['legs'][$order['index']]['duration'];
                            break;
                        }
                    }
                }
            }

            // Step 4: Save updated checkpoints back to the database
            foreach ($checkpoints as $checkpoint) {
                $trip->checkpoints()->where('id', $checkpoint['id'])->update([
                    'sort_order_in_trip' => $checkpoint['sort_order_in_trip'],
                    'eta' => $checkpoint['eta'],
                ]);
            }

            $trip->duration = $data['trips'][0]['duration'];
            $trip->distance = $data['trips'][0]['distance'];
            $trip->save();

        } catch (\Exception $e){}
    }

    /**
     * Apply status filters to the query based on the request.
     *
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function applyStatusFilters(Request $request, $query)
    {
        $pending = $request->has('pending');
        $started = $request->has('started');
        $closed = $request->has('closed');
        $allTrips = $request->has('allTrips');

        if (!$allTrips && ($pending || $started || $closed)) {
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
            "id" => "0",
            "checkpoint_type" => "other",
            "checkpoint_related_id" => 9,
            "checkpoint_notes" => __('Default starting checkpoint')
        ];

        $default_starting_checkpoint_user = User::find(9);
        $long_lat_array = $this->userService->getUserLongLat($default_starting_checkpoint_user);
        $checkpoints_array[] = $this->formatCheckpointsArray($default_starting_checkpoint, [], 0, User::class,  $long_lat_array);
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
        $admin_user_id = User::where('user_type', 'admin')->first()->id;

        if ($request->search) {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%' . $sort_search . '%')
                ->orWhere('invoice_number', 'like', '%' . $sort_search . '%')
                ->orWhere('shipping_address', 'like', '%' . $sort_search . '%')
                ->orWhereHas('customer', function ($query) use ($sort_search) {
                    $query->where('name', 'like', '%' . $sort_search . '%')
                        ->orWhere('phone', 'like', '%' . $sort_search . '%')
                        ->orWhere('AccSysID', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('rep', function ($query) use ($sort_search) {
                    $query->where('name', 'like', '%' . $sort_search . '%')
                        ->orWhere('AccSysID', 'like', '%' . $sort_search . '%');
                });
        }

        if ($delivery_status != null) {
            $orders = $orders->where('delivery_status', $delivery_status);
        }

        if ($date != null) {
            $orders = $orders->where('created_at', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])) . '  00:00:00')
                ->where('created_at', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])) . '  23:59:59');
        }

        $orders = $orders->paginate(15);
        return view('backend.trips.orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
    }

    public function bulk_change_delivery_status(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $order_id) {
                $order = Order::findOrFail($order_id);
                if ($request->delivery_status == 'confirmed'){
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
