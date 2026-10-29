<?php

namespace App\Mail;

class ConversationMailManager extends LocalizedMail
{
    public function build(): static
    {
        $this->localizeMessage();

        return $this->text('shop.emails.plain.message')->view('shop.emails.conversation')
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
