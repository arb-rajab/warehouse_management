<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\User;
use Google\Client as GoogleClient;

class AdminNotificationController extends Controller
{
    public function getAllNotifications()
    {
        $notifications = Notification::with('user')->paginate('10');

        return view('backend.customer.notifications.index', compact('notifications'));
    }

    public function deleteNotification($id)
    {
        $notification = Notification::find($id);

        if ($notification) {
            $notification->delete();
            return back()->with('success', 'Notification deleted.');
        }

        return back()->with('error', 'Notification not found.');
    }

    public function createNotification()
    {
        $customers = User::where('user_type', 'customer')->orderBy('name', 'desc')->get();
        return view('backend.customer.notifications.create', compact('customers'));
    }

    public function sendFcmNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        $user = User::find($request->user_id);
        $fcm = $user->device_key;

        if (!$fcm) {
            return response()->json(['message' => 'User does not have a device token'], 400);
        }

        $title = $request->title;
        $description = $request->body;
        $projectId = config('services.fcm.project_id'); // INSERT COPIED PROJECT ID


        $credentialsFilePath = base_path('storage/app/json/file.json');

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();

        $access_token = $token['access_token'];

        $headers = [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json'
        ];

        $data = [
            "message" => [
                "token" => $fcm,
                "notification" => [
                    "title" => $title,
                    "body" => $description,
                ],
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return redirect()->route('admin.notifications.all');

            // return response()->json([
            //     'message' => 'Curl Error: ' . $err
            // ], 500);
        } else {

            Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $description,
                'status' => 'sent',
                'response' => json_encode(json_decode($response, true)),
            ]);

            return redirect()->route('admin.notifications.all');
        }
    }
}
