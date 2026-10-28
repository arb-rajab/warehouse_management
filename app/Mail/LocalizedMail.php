<?php

namespace App\Mail;

use App\Support\StoreLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class LocalizedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $array */
    public function __construct(public array $array, ?object $recipient = null)
    {
        $this->locale(StoreLocale::deliveryLocale($recipient, $array['locale'] ?? null));
    }

    /**
     * Restore the translator separately: the content locale may be the legacy sa code.
     *
     * @template TReturn
     *
     * @param  string|null  $locale
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withLocale($locale, $callback)
    {
        $translatorLocale = app('translator')->getLocale();

        try {
            return parent::withLocale(StoreLocale::deliveryLocale(locale: $locale), $callback);
        } finally {
            app('translator')->setLocale($translatorLocale);
        }
    }

    /** Resolve static copy at delivery time; custom/newsletter copy stays content. */
    protected function localizeMessage(): void
    {
        $this->locale(StoreLocale::deliveryLocale(locale: $this->locale));
        foreach (['subject', 'content', 'details'] as $field) {
            if (isset($this->array[$field.'_key'])) {
                $this->array[$field] = __($this->array[$field.'_key'], $this->array[$field.'_parameters'] ?? [], $this->locale);
            }
        }
        $this->array['locale'] = $this->locale;
        $this->array['details_are_html'] ??= static::class === SupportMailManager::class;
        $this->array['include_terms'] ??= static::class === SecondEmailVerifyMailManager::class;
        $this->array['action_key'] ??= match (static::class) {
            SupportMailManager::class => 'delivery.mail.see_ticket',
            AuctionBidMailManager::class => 'storefront.product.change_bid',
            SecondEmailVerifyMailManager::class => 'storefront.common.agree',
            default => 'delivery.mail.see_details',
        };
        $this->with(['array' => $this->array, 'mailLocale' => $this->locale]);
    }
}
