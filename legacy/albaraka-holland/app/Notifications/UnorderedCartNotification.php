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


    public function __construct($userName, $userId, $cartCount, $message)
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
            'message' => $this->message,
        ];
    }
}
