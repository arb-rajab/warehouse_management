<?php

namespace App\Notifications;

use App\Mail\EmailManager;
use App\Models\StoreUser;
use Auth;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AppEmailVerificationNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $code  The clear-text code StoreVerificationCodes::issue() returned.
     */
    public function __construct(public readonly string $code)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $locale = \App\Support\StoreLocale::deliveryLocale($notifiable, $this->locale);
        $array['locale'] = $locale;
        $array['view'] = 'shop.emails.app_verification';
        $array['subject'] = __('storefront.auth.email_verification', [], $locale);
        $array['content'] = __('storefront.auth.verification_code_message', ['code' => $this->code], $locale);

        return (new MailMessage)
            ->view(['html' => 'shop.emails.app_verification', 'text' => 'shop.emails.plain.message'], ['array' => $array])
            ->subject(__('storefront.auth.verification_subject', ['app' => config('app.name')], $locale));
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
