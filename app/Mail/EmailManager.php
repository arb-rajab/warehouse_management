<?php

namespace App\Mail;

class EmailManager extends LocalizedMail
{
    public function build(): static
    {
        $this->localizeMessage();

        return $this->text('shop.emails.plain.message')->view($this->array['view'])
            ->from($this->array['from'], config('mail.from.name'))
            ->subject($this->array['subject']);
    }
}
