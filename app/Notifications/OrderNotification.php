<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification
{
    use Queueable;

    protected $order_notification;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($order_notification)
    {
        $this->order_notification = $order_notification;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
//        return ['mail', 'database'];
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $locale = \App\Support\StoreLocale::deliveryLocale($notifiable, $this->locale);
        $array = [
            'locale' => $locale,
            'subject' => __('messages.order_updated_title', [], $locale),
            'content' => __('delivery.notifications.order_updated', [
                'code' => $this->order_notification['order_code'],
                'status' => \App\Support\StoreLocale::status($this->order_notification['status'], $locale),
            ], $locale),
            'link' => url('/'),
        ];

        return (new MailMessage)
            ->view(['html' => 'shop.emails.order_notification', 'text' => 'shop.emails.plain.message'], ['array' => $array])
            ->subject($array['subject']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'order_id'      => $this->order_notification['order_id'],
            'order_code'    => $this->order_notification['order_code'],
            'user_id'       => $this->order_notification['user_id'],
            'seller_id'     => $this->order_notification['seller_id'],
            'status'        => $this->order_notification['status'],
        ];
    }
}
