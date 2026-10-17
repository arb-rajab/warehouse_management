<?php

namespace App\Http\Resources\Store;

use App\Models\Checkpoint;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A truck card of the trip map picker, with what the Blade picker printed:
 * the name, the pallet capacity, the first photo's upload id, the latest
 * open trip with its driver (the trip a card adds stops to and deletes),
 * and every open trip (latest first) with each stop's customer (for an
 * order, the customer it was placed for).
 * Expects `trips` set to the open trips, latest first, each with its
 * `checkpoints` in stop order (`TripMapController::loadOpenTrips()`).
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $max_pallets_number
 * @property-read string|null $photos
 */
class TripMapTruckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Truck $truck */
        $truck = $this->resource;
        /** @var iterable<Trip> $trips */
        $trips = $truck->getRelationValue('trips');
        $latest = null;
        $openTrips = [];
        foreach ($trips as $trip) {
            $latest ??= $trip;
            /** @var Collection<int, Checkpoint> $checkpoints */
            $checkpoints = $trip->getRelationValue('checkpoints');
            $openTrips[] = [
                'id' => $trip->id,
                'code' => (string) $trip->code,
                'stops' => $checkpoints->map(function (Checkpoint $checkpoint): array {
                    $customer = $checkpoint->getCustomerForCheckpoint();

                    return [
                        'id' => $checkpoint->id,
                        'customer' => $customer instanceof StoreUser ? ['id' => $customer->id, 'name' => $customer->name] : null,
                    ];
                })->values()->all(),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'max_pallets_number' => $this->max_pallets_number,
            'photo' => TripCheckpointResource::uploadIds($this->photos)[0] ?? null,
            'trip' => $latest === null ? null : [
                'id' => $latest->id,
                'code' => (string) $latest->code,
                'driver' => $latest->driver === null ? null : ['id' => $latest->driver->id, 'name' => $latest->driver->name],
            ],
            'open_trips' => $openTrips,
        ];
    }
}
