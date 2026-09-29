<?php

namespace App\Listeners;

use App\Events\SetTruckAvailability;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateTruckAvailability
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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
