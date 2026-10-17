<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Validation\ValidationException;

/**
 * Locks a trip's truck and refuses one another open trip holds, shared by
 * the trip map picker's writer (`Store\Admin\TripMapController`) and
 * the trip forms and the trip list's status action
 * (`Store\Admin\TripController`).
 */
trait ReservesTripTrucks
{
    /**
     * Lock the truck; unless `$reserve` is false, refuse it with a `truck_id`
     * error when another open trip holds it, or when it is marked unavailable
     * and `$trip` does not already hold it.
     */
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
}
