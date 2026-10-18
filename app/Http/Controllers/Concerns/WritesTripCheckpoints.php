<?php

namespace App\Http\Controllers\Concerns;

use App\Models\StoreUser;
use App\Models\Trip;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * The trip writers' shared steps, used by the trip forms
 * (`Store\Admin\TripController`) and the trip map picker's writer
 * (`Store\Admin\TripMapController::store`): the departure checkpoint
 * (store user 9), a checkpoint row, the trip row and the shortest-path sort.
 * Moved unchanged from the root `Trip\TripController`, since removed.
 */
trait WritesTripCheckpoints
{
    /**
     * A new open trip from the validated form: `driver_id`, `truck_id`,
     * `due_date` (today when missing) and `notes`, with a time-stamped code.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createTrip(array $data): Trip
    {
        return Trip::create([
            'driver_id' => $data['driver_id'] ?? null,
            'truck_id' => $data['truck_id'],
            'due_date' => $data['due_date'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'code' => now()->format('Ymd-His'),
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, mixed>
     */
    protected function formatCheckpointsArray(array $checkpoint, mixed $address, int $key, string $relationable_type, mixed $long_lat_array = []): array
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

    /**
     * Prepends the departure checkpoint: store user 9, the configured
     * starting point. A field error (`departure`) when that user is missing.
     *
     * @param  array<int, array<string, mixed>>  $checkpoints_array
     */
    protected function applyDefaultFirstCheckpoint(array &$checkpoints_array): void
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
        $long_lat_array = app(UserService::class)->getUserLongLat($default_starting_checkpoint_user);
        $checkpoints_array[] = $this->formatCheckpointsArray($default_starting_checkpoint, [], 0, StoreUser::class, $long_lat_array);
    }

    protected function hasValidCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && (float) $latitude >= -90 && (float) $latitude <= 90
            && (float) $longitude >= -180 && (float) $longitude <= 180;
    }

    /**
     * Reorders the checkpoints that have coordinates by the routing service's
     * shortest trip and stores the trip's duration and distance. A routing
     * outage leaves the order as posted.
     */
    protected function sortBasedOnShortestPath(Trip $trip): void
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
}
