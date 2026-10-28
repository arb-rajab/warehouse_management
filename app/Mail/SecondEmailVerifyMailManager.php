<?php

namespace App\Mail;

class SecondEmailVerifyMailManager extends LocalizedMail
{
    public function build(): static
    {
        $this->localizeMessage();

        return $this->text('shop.emails.plain.message')->view('shop.emails.verification')
            ->from($this->array['from'], config('mail.from.name'))
            ->subject($this->array['subject']);
    }
}
