<?php

namespace App\Mail;

class SupportMailManager extends LocalizedMail
{
    public function build(): static
    {
        $this->localizeMessage();

        return $this->text('shop.emails.plain.message')->view($this->array['view'])
            ->from($this->array['from'], config('mail.from.name'))
            ->subject($this->array['subject'])
            ->with([
                'content' => $this->array['content'],
                'link' => $this->array['link'],
                'sender' => $this->array['sender'],
                'details' => $this->array['details'],
            ]);
    }
}
