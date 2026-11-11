<?php

namespace App\Mail;

use App\Support\StoreLocale;

/**
 * Tells a Super Admin, or the shop's previous from-address, that the SMTP
 * settings were changed: by whom, when, from which IP and which fields (with
 * how each changed). It carries no setting value and no secret
 * (SmtpSettingsChangeNotifier, .ai/rules/admin-env-overrides.md "Security").
 *
 * `$array`: `changes` (field => set|changed|removed), `actor_name`,
 * `actor_email`, `changed_at` (formatted), `ip`, `link`, `from_address`,
 * `from_name` (the previous effective sender, null for the mailer's own).
 */
class SmtpSettingsChangedMail extends LocalizedMail
{
    public function build(): static
    {
        $this->locale(StoreLocale::deliveryLocale(locale: $this->locale));
        $locale = $this->locale;
        $fields = collect($this->array['changes'])
            ->map(fn (string $kind, string $field): string => __('delivery.mail.smtp_changed.field_change', [
                'field' => __('delivery.mail.smtp_changed.fields.'.$field, [], $locale),
                'change' => __('delivery.mail.smtp_changed.kinds.'.$kind, [], $locale),
            ], $locale))
            ->values()
            ->all();
        $subject = __('delivery.mail.smtp_changed.subject', ['shop' => __('messages.brand_name', [], $locale)], $locale);

        if (filled($this->array['from_address'] ?? null)) {
            $this->from((string) $this->array['from_address'], $this->array['from_name'] ?? null);
        }

        return $this->view('shop.emails.smtp_settings_changed')
            ->text('shop.emails.plain.smtp_settings_changed')
            ->subject($subject)
            ->with([
                'mailLocale' => $locale,
                'array' => ['locale' => $locale],
                'subjectLine' => $subject,
                'actorName' => (string) $this->array['actor_name'],
                'actorEmail' => (string) $this->array['actor_email'],
                'changedAt' => (string) $this->array['changed_at'],
                'ip' => (string) ($this->array['ip'] ?? '-'),
                'fields' => $fields,
                'link' => (string) $this->array['link'],
            ]);
    }
}
