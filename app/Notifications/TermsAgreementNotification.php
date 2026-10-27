<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TermsAgreementNotification extends Notification
{
    use Queueable;

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
        $notifiable->verification_code = encrypt($notifiable->id);
        $notifiable->save();

        $array['view'] = 'shop.emails.verification';
        $array['subject'] = translate('Terms Agreement');
        $array['content'] = translate('By Clicking The Button Below You Agree To The Terms & Conditions');
        $array['link'] = route('terms.activation', $notifiable->verification_code);

        return (new MailMessage)
            ->view('shop.emails.terms', ['array' => $array])
            ->subject(translate('Terms Agreements - ').config('app.name'));
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
