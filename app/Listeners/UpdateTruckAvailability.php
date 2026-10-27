<?php

namespace App\Listeners;

use App\Events\SetTruckAvailability;

class UpdateTruckAvailability
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(SetTruckAvailability $event)
    {
        $truck = $event->truck;
        $is_available = $event->status;
        $truck->is_available = $is_available;
        $truck->save();
    }
}
