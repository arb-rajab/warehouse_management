<?php

namespace App\Mail;

class InvoiceEmailManager extends LocalizedMail
{
    public function build(): static
    {
        $this->localizeMessage();

        return $this->text('shop.emails.plain.invoice')->view($this->array['view'])
            ->from($this->array['from'], config('mail.from.name'))
            ->subject($this->array['subject'])
            ->with([
                'order' => $this->array['order'],
            ]);
    }
}
