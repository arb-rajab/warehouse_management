<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDriverTripNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $body;
    protected $trip;


    public function __construct($message, $body, $trip)
    {
        $this->message = $message;
        $this->body = $body;
        $this->trip = $trip;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
            'body' => $this->body,
            'trip_id' => $this->trip->id,
            'trip_code' => $this->trip->code,
            'truck_name' => $this->trip->truck?->name,
            'truck_images' => get_images_path($this->trip->truck->photos),
        ];
    }
}
