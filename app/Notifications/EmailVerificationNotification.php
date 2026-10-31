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

class EmailVerificationNotification extends Notification
{
    use Queueable;

    /**
     * How long the emailed confirmation link works.
     */
    public const LINK_LIFETIME_HOURS = 24;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $locale = \App\Support\StoreLocale::deliveryLocale($notifiable, $this->locale);
        $array['locale'] = $locale;
        $array['include_terms'] = true;
        $array['action_key'] = 'storefront.common.agree';
        $array['view'] = 'shop.emails.verification';
        $array['subject'] = __('storefront.auth.verification_terms', [], $locale);
        $array['content'] = __('storefront.auth.agree_to_terms', [], $locale);
        // Signed and expiring, and bound to the address it was sent to: it
        // used to carry `users.verification_code`, which never expired and
        // is the same column the six-digit reset codes went into.
        $array['link'] = URL::temporarySignedRoute(
            'email.verification.confirmation',
            now()->addHours(self::LINK_LIFETIME_HOURS),
            ['id' => $notifiable->getKey(), 'hash' => sha1((string) $notifiable->email)],
        );

        return (new MailMessage)
            ->view(['html' => 'shop.emails.verification', 'text' => 'shop.emails.plain.message'], ['array' => $array])
            ->subject(__('storefront.auth.verification_subject', ['app' => config('app.name')], $locale));
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
