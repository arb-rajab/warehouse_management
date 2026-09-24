<?php
namespace App\Services;

use App\Models\User;
use App\Notifications\NewDriverTripNotification;
use App\Utility\NotificationUtility;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;

class DriverService{

    public function notifyDriver($trip): void
    {
        $driver = User::find($trip->driver_id);
        if($driver){

            // notify the user
            Notification::send($driver, new NewDriverTripNotification(translate('New trip assigned!'), translate("You have been assigned to a new trip, trip code: {$trip->code}!"), $trip));

            if ($driver->device_token != null) {
                $data = collect();
                $data->device_token = $driver->device_token;
                $data->title = translate('New trip assigned!');
                $data->text = translate("You have been assigned to a new trip, trip code: {$trip->code}!");

                $data->type = "trip";
                $data->id = $trip->id;
                $data->user_id = $driver->id;

                NotificationUtility::sendPushNotificationToUser($driver, $data->title, $data->text, $link = null);
            }
        }
    }

}
