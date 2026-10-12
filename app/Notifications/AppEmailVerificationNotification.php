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
        $array['view'] = 'shop.emails.app_verification';
        $array['subject'] = translate('Email Verification');
        $array['content'] = translate('Please enter the code:'.$this->code);

        return (new MailMessage)
            ->view('shop.emails.app_verification', ['array' => $array])
            ->subject(translate('Email Verification - ').config('app.name'));
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
