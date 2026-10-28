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
        $locale = \App\Support\StoreLocale::deliveryLocale($notifiable, $this->locale);
        $array['locale'] = $locale;
        $array['include_terms'] = true;
        $array['action_key'] = 'storefront.common.agree';
        $notifiable->verification_code = encrypt($notifiable->id);
        $notifiable->save();

        $array['view'] = 'shop.emails.verification';
        $array['subject'] = __('storefront.auth.terms_agreement', [], $locale);
        $array['content'] = __('storefront.auth.agree_to_terms', [], $locale);
        $array['link'] = route('terms.activation', $notifiable->verification_code);

        return (new MailMessage)
            ->view(['html' => 'shop.emails.terms', 'text' => 'shop.emails.plain.message'], ['array' => $array])
            ->subject(__('storefront.auth.terms_subject', ['app' => config('app.name')], $locale));
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
