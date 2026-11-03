<?php

namespace App\Utility;

use App\Http\Controllers\OTPVerificationController;
use App\Mail\InvoiceEmailManager;
use App\Models\FirebaseNotification;
use App\Models\Order;
use App\Models\SmsTemplate;
use App\Models\StoreUser;
use App\Notifications\OrderNotification;
use App\Services\FcmPushService;
use Illuminate\Support\Facades\Notification;
use Mail;

class NotificationUtility
{
    public static function sendOrderPlacedNotification($order)
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
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $locale = \App\Support\StoreLocale::deliveryLocale($order->user);
            self::sendOrderFirebaseNotification($order, __('delivery.notifications.order_placed', [], $locale), __('delivery.notifications.order_placed_body', ['code' => $order->code], $locale));
        }
    }

    public static function sendNotification($order, $order_status)
    {
        // The shop's own admin (the first one): none exists on a fresh database.
        $adminId = StoreUser::where('user_type', 'admin')->orderBy('id')->value('id');

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

    /**
     * Push `$req->title` and `$req->text` to `$req->device_token` and keep a
     * record of it. `type` and `id` (the order id) reach the app as the data
     * keys `item_type` and `item_type_id`. A token Firebase no longer knows is
     * cleared from the account, so the next order does not try it again.
     */
    public static function sendFirebaseNotification($req): string
    {
        $result = app(FcmPushService::class)->send((string) $req->device_token, (string) $req->title, (string) $req->text, [
            'item_type' => $req->type,
            'item_type_id' => $req->id,
        ]);

        if ($result === FcmPushService::SENT) {
            $firebase_notification = new FirebaseNotification;
            $firebase_notification->title = $req->title;
            $firebase_notification->text = $req->text;
            $firebase_notification->item_type = $req->type;
            $firebase_notification->item_type_id = $req->id;
            $firebase_notification->receiver_id = $req->user_id;

            $firebase_notification->save();
        } elseif ($result === FcmPushService::INVALID_TOKEN) {
            StoreUser::where('device_token', $req->device_token)->update(['device_token' => null]);
        }

        return $result;
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
        $result = app(FcmPushService::class)->send((string) $user->device_token, (string) $title, (string) $body, $link ? ['link' => $link] : []);

        if ($result === FcmPushService::INVALID_TOKEN) {
            StoreUser::where('device_token', $user->device_token)->update(['device_token' => null]);
        }

        return response()->json([
            'message' => __('api.common.notification_status'),
            'response' => ['result' => $result],
        ]);
    }
}
