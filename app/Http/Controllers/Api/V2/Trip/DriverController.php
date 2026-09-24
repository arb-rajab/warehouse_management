<?php

namespace App\Http\Controllers\Api\V2\Trip;

use App\Enums\CheckpointStatus;
use App\Enums\CheckpointType;
use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\ScanTruckQrRequest;
use App\Http\Requests\Trips\UpdateCheckpointRequest;
use App\Http\Requests\Trips\UpdateTripRequest;
use App\Http\Resources\V2\Trip\TripResource;
use App\Models\Checkpoint;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\TripService;
use App\Services\UserService;
use Auth;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    protected function driverService()
    {
        return app(DriverService::class);
    }
    protected function userService()
    {
        return app(UserService::class);
    }
    protected function tripService()
    {
        return app(TripService::class);
    }

    public function getTripsHistory()
    {
        $trips = Auth::user()->trips()->where('status', TripStatus::Closed)->get();

        return response()->json([
            'data' => TripResource::collection($trips)
        ]);
    }

    public function getActiveTrip()
    {
        $trip = Auth::user()->activeTrip();

        return response()->json([
            'data' => $trip ? new TripResource($trip) : null
        ]);
    }

    public function updateCheckpointNotes(Request $request)
    {
        $checkpoint = Checkpoint::find($request->checkpoint_id);

        if ($checkpoint) {
            $checkpoint->notes = $request->notes;
            $checkpoint->save();

            return response()->json([
                'message' => translate('Checkpoint updated successfully'),
                'data' => new TripResource($checkpoint->trip)
            ]);
        } else {
            return response()->json([
                'message' => translate('Checkpoint not found'),
                'data' => null
            ], 404);
        }
    }

    public function updateCheckpoint(UpdateCheckpointRequest $request)
    {
        $trip = Auth::user()->activeTrip();

        if ($trip) {
            $checkpoint = $trip->checkpoints->where('id', $request->checkpoint_id)->first();

            if ($checkpoint) {
                if ($request->hasFile('photos')) {
                    $photos_ids = $this->tripService()->uploadFiles($request->photos);

                    // Join the array values into a single string separated by commas
                    $photos_ids_string = implode(',', $photos_ids);
                    $checkpoint->photos = $photos_ids_string;
                }

                if ($request->hasFile('cmr_file')) {
                    $this->tripService()->process_cmr_file($checkpoint->id, $request);
                }

                $checkpoint->status = CheckpointStatus::from($request->status);
                $checkpoint->notes = $request->notes;
                $checkpoint->longitudes = $request->longitudes;
                $checkpoint->latitudes = $request->latitudes;
                $checkpoint->arrived_at = ($checkpoint->arrived_at == null && $checkpoint->status == CheckpointStatus::Delivered) ? now() : $checkpoint->arrived_at;
                $checkpoint->save();

                if($checkpoint->type == CheckpointType::Delivery && $checkpoint->status == CheckpointStatus::Delivered){
                    $this->close_related_order($checkpoint);
                }

                return response()->json([
                    'message' => translate('Checkpoint updated successfully'),
                    'data' => new TripResource($trip)
                ]);
            } else {
                return response()->json([
                    'message' => translate('Checkpoint not found'),
                    'data' => null
                ], 404);
            }
        } else {
            return response()->json([
                'message' => translate('There is no active Trip for this driver'),
                'data' => null
            ], 404);
        }
    }

    public function updateTrip(UpdateTripRequest $request)
    {
        $trip = Auth::user()->activeTrip();

        if ($trip) {

            $trip->status = TripStatus::from($request->status);
            $trip->notes = $request->notes;

            $truck = Truck::find($trip->truck_id);
            if ($trip->status === TripStatus::Started){
                $trip->started_at = now();
                event(new SetTruckAvailability($trip, $truck, false));
                $this->updateCheckpointsStatuses($trip);
            } elseif ($trip->status === TripStatus::Closed) {
                $trip->closed_at = now();
                $trip->active = false;
                event(new SetTruckAvailability($trip, $truck, true));

                $this->activate_next_trip(Auth::user(), $trip->id);
            }

            $trip->save();

            return response()->json([
                'message' => translate('Trip updated successfully'),
                'data' => new TripResource($trip)
            ]);
        } else {
            return response()->json([
                'message' => translate('There is no active Trip for this driver'),
                'data' => null
            ], 404);
        }
    }

    public function assignTruckByQrScan(ScanTruckQrRequest $request)
    {
        $current_trip = Auth::user()->activeTrip();
        $scanned_truck_last_trip = Truck::where('license_plate', $request->code)->first()->lastTrip();

        if(!$scanned_truck_last_trip){
            return response()->json([
                'message' => translate('This truck has no trip'),
                'data' => null
            ], 400);
        }

        if ($current_trip) {
            if ($current_trip->status === TripStatus::Started->value) {
                return response()->json([
                    'message' => translate('A trip has already started'),
                    'data' => null
                ], 400);
            }

            $current_trip->active = false;
            $current_trip->driver_id = null;
            $current_trip->save();
        }

        if($scanned_truck_last_trip->status === TripStatus::Closed->value){
            return response()->json([
                'message' => translate('This trip has already closed'),
                'data' => null
            ], 400);
        }

        $scanned_truck_last_trip->driver_id = Auth::id();
        $scanned_truck_last_trip->active = true;
        $scanned_truck_last_trip->status = TripStatus::Pending;
        $scanned_truck_last_trip->save();

        $this->driverService()->notifyDriver($scanned_truck_last_trip);

        return response()->json([
            'message' => translate('Trip assigned successfully'),
            'data' => new TripResource($scanned_truck_last_trip)
        ]);
    }

    protected function close_related_order($checkpoint)
    {
        if($checkpoint->order){
            $checkpoint->order->delivery_status = 'delivered';
            $checkpoint->order->save();
        }
    }

    protected function activate_next_trip($user, $finished_trip_id)
    {
        $next_trip = $user->trips()->where('status', '<>', TripStatus::Closed)->where('id', '<>', $finished_trip_id)->orderBy('due_date')->first();
        if($next_trip){
            $this->driverService()->notifyDriver($next_trip);
            $next_trip->active = true;
            $next_trip->save();
        }
    }

    public function getAvailableVehicles(Request $request)
    {
        $date = $request->input('date'); // The selected date
        $excluded_trip_id = $request->input('excluded_trip_id') ?? 0;

        // Fetch drivers that do not have any active trips on the selected date
        $drivers = \App\Models\User::drivers()
            ->whereDoesntHave('trips', function ($query) use ($date, $excluded_trip_id) {
                $query->whereDate('due_date', $date)
                        ->where('id', '<>', $excluded_trip_id);
            })
            ->get();

        // Fetch trucks that do not have any active trips on the selected date
        $trucks = \App\Models\Truck::whereDoesntHave('trips', function ($query) use ($date, $excluded_trip_id) {
            $query->whereDate('due_date', $date)
                ->where('id', '<>', $excluded_trip_id); // Exclude other trips on the same date
                })
                ->orWhereHas('trips', function ($query) use ($excluded_trip_id) {
                    $query->where('id', $excluded_trip_id); // Include the excluded trip ID
                })
            ->get();

        // Return the available drivers and trucks as JSON
        return response()->json([
            'drivers' => $drivers,
            'trucks' => $trucks
        ]);

    }

    public function getLongLatitude($id, Request $request)
    {
        if($request->type == 'order'){
            $order = \App\Models\Order::find($id);
            $user = $order->customer;
        }else{
            $user = \App\Models\User::find($id);
        }

        if($user){
            return $this->userService()->getUserLongLat($user, $order ?? null);
        }
        return response()->json(['error' => 'No post code or coordinates provided for this user'], 404);
    }

    protected function updateCheckpointsStatuses($trip)
    {
        $checkpoints = $trip->checkpoints;
        if($checkpoints){
            $first = true;
            foreach ($checkpoints as $checkpoint) {
                if ($first) {
                    $checkpoint->status = 'delivered';
                    $checkpoint->save();
                    $first = false;
                }

                if ($checkpoint->isOrder()) {
                    $checkpoint->getOrder()?->update([
                        'delivery_status' => 'on_delivery',
                    ]);
                }
            }
        }
    }

}
