<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Trip;
use App\Models\Truck;

class SetTruckAvailability
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trip;
    public $truck;
    public $status;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Trip $trip, Truck $truck, $status = true)
    {
        $this->trip = $trip;
        $this->truck = $truck;
        $this->status = $status;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('trip.' . $this->trip->id);
    }
}
