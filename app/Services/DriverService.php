<?php
namespace App\Services;

use App\Models\StoreUser;
use App\Notifications\NewDriverTripNotification;
use App\Utility\NotificationUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class DriverService{

    public function notifyDriver($trip): void
    {
        $driver = StoreUser::find($trip->driver_id);
        if($driver){

            $locale = \App\Support\StoreLocale::deliveryLocale($driver);
            $title = __('delivery.notifications.trip_assigned', [], $locale);
            $body = __('delivery.notifications.trip_body', ['code' => $trip->code], $locale);

            // notify the user
            Notification::send($driver, new NewDriverTripNotification($title, $body, $trip));

            if ($driver->device_token != null) {
                $data = collect();
                $data->device_token = $driver->device_token;
                $data->title = $title;
                $data->text = $body;

                $data->type = "trip";
                $data->id = $trip->id;
                $data->user_id = $driver->id;

                NotificationUtility::sendPushNotificationToUser($driver, $data->title, $data->text, $link = null);
            }
        }
    }

}
