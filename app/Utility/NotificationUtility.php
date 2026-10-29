<?php

namespace App\Utility;

use App\Http\Controllers\OTPVerificationController;
use App\Mail\InvoiceEmailManager;
use App\Models\FirebaseNotification;
use App\Models\Order;
use App\Models\SmsTemplate;
use App\Models\StoreUser;
use App\Notifications\OrderNotification;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Notification;
use Mail;

class NotificationUtility
{
    public static function sendOrderPlacedNotification($order, $request = null)
    {
        //sends email to customer with the invoice pdf attached
        $array['view'] = 'shop.emails.invoice';
        $array['subject_key'] = 'delivery.mail.order_placed_subject';
        $array['subject_parameters'] = ['code' => $order->code];
        $array['from'] = config('mail.from.address');
        $array['order'] = $order;
        try {
          //  Mail::to($order->user->email)->queue(new InvoiceEmailManager($array, $order->user));
           // Mail::to($order->orderDetails->first()->product->user->email)->queue(new InvoiceEmailManager($array, $order->orderDetails->first()->product->user));
        } catch (\Exception $e) {

        }

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'order_placement')->first()->status == 1) {
            try {
                $otpController = new OTPVerificationController;
                $otpController->send_order_code($order);
            } catch (\Exception $e) {

            }
        }

        //sends Notifications to user
        self::sendNotification($order, 'placed');
        if ($request !=null && get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $locale = \App\Support\StoreLocale::deliveryLocale($order->user);
            self::sendOrderFirebaseNotification($order, __('delivery.notifications.order_placed', [], $locale), __('delivery.notifications.order_placed_body', ['code' => $order->code], $locale));
        }
    }

    public static function sendNotification($order, $order_status)
    {
        // The shop's own admin (not the vendor's super-admin): none exists on a fresh database.
        $adminId = StoreUser::where('user_type', 'admin')->where('raizer_admin', null)->value('id');

        if ($order->seller_id == $adminId) {
            $users = StoreUser::findMany([$order->user->id, $order->seller_id]);
        } else {
            $users = StoreUser::findMany(array_filter([$order->user->id, $order->seller_id, $adminId]));
        }

        $order_notification = [];
        $order_notification['order_id'] = $order->id;
        $order_notification['order_code'] = $order->code;
        $order_notification['user_id'] = $order->user_id;
        $order_notification['seller_id'] = $order->seller_id;
        $order_notification['status'] = $order_status;

        Notification::send($users, new OrderNotification($order_notification));
    }

    public static function sendFirebaseNotification($req)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $fields =
        [
            'to' => $req->device_token,
            'notification' => [
                'body' => $req->text,
                'title' => $req->title,
                'sound' => 'default' /*Default sound*/
            ],
            'data' => [
                'item_type' => $req->type,
                'item_type_id' => $req->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ];

        //$fields = json_encode($arrayToSend);
        $headers = [
            'Authorization: key=' . config('services.fcm.server_key'),
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);

        $firebase_notification = new FirebaseNotification;
        $firebase_notification->title = $req->title;
        $firebase_notification->text = $req->text;
        $firebase_notification->item_type = $req->type;
        $firebase_notification->item_type_id = $req->id;
        $firebase_notification->receiver_id = $req->user_id;

        $firebase_notification->save();
    }

    // new Google firebase logic
    /**
     * Push an order update to the order's customer through sendFirebaseNotification().
     */
    public static function sendLocalizedOrderUpdate(Order $order, string $status): void
    {
        $locale = \App\Support\StoreLocale::deliveryLocale($order->user);
        self::sendOrderFirebaseNotification($order,
            __('messages.order_updated_title', [], $locale),
            __('delivery.notifications.order_updated', ['code' => $order->code, 'status' => \App\Support\StoreLocale::status($status, $locale)], $locale));
    }

    public static function sendOrderFirebaseNotification(Order $order, string $title, string $text): void
    {
        self::sendFirebaseNotification((object) [
            'device_token' => $order->user->device_token,
            'title' => $title,
            'text' => $text,
            'type' => 'order',
            'id' => $order->id,
            'user_id' => $order->user->id,
        ]);
    }

    public static function sendPushNotificationToUser($user, $title, $body, $link = null)
    {
        $credentialsFilePath = storage_path(config('services.fcm.firebase_credentials'));

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();

        $token = $client->getAccessToken();

        $access_token = $token['access_token'];
        $apiurl = "https://fcm.googleapis.com/v1/projects/" . config('services.fcm.firebase_project') . "/messages:send";

        $headers = [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json',
        ];

        $fcmToken = $user->device_token;

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        $payload = json_encode($payload);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

        $response = curl_exec($ch);

        return response()->json([
            'message' => __('api.common.notification_status'),
            'response' => json_decode($response, true)
        ]);
    }
}
