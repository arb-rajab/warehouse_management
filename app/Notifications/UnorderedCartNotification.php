<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnorderedCartNotification extends Notification
{
    use Queueable;

    protected $userName;
    protected $userId;
    protected $cartCount;
    protected $message;


    public function __construct($userName, $userId, $cartCount, $message = null)
    {
        $this->userName = $userName;
        $this->userId = $userId;
        $this->cartCount = $cartCount;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'user_name' => $this->userName,
            'user_id' => $this->userId,
            'cart_count' => $this->cartCount,
            'message' => $this->message ?? trans_choice('delivery.notifications.unordered_cart', $this->cartCount, ['count' => $this->cartCount], \App\Support\StoreLocale::deliveryLocale($notifiable, $this->locale)),
        ];
    }
}
