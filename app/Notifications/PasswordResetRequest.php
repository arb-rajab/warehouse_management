<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetRequest extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */

    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
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
        $url = config('app.url') . '/password/reset/' . $this->token;
        return (new MailMessage)
            ->view('shop.emails.password_reset', ['array' => ['locale' => $locale, 'link' => $url]])
            ->text('shop.emails.plain.password_reset', ['array' => ['locale' => $locale, 'link' => $url]])
            ->subject(__('storefront.auth.password_reset_subject', [], $locale));
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
            //
        ];
    }
}
