<?php

namespace App\Http\Controllers\Api\V2\Trip;

use App\Http\Controllers\Controller;

class DriverNotificationController extends Controller
{
    public function getAllNotification()
    {
        $notifications = auth()->user()->notifications()
            ->where('type', 'App\Notifications\NewDriverTripNotification')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        auth()->user()->unreadNotifications->markAsRead();

        // Extract the values from each notification's 'data' field
        $notificationData = $notifications->map(function ($notification) {
            return [
                'message' => $notification->data['message'],
                'body' => $notification->data['body'],
                'trip' => $notification->data['trip_id'],
                'trip_code' => $notification->data['trip_code'],
                'truck_name' => $notification->data['truck_name'],
                'truck_images' => $notification->data['truck_images'],
                'read_at' => $notification->read_at,
            ];
        });

        return response()->json([
            'data' => $notificationData,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'from' => $notifications->firstItem(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }
}
