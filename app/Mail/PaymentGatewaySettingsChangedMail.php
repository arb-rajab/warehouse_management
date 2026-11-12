<?php

namespace App\Mail;

use App\Support\StoreLocale;
use App\Support\StorePaymentGateways;

/**
 * Tells a Super Admin that the payment gateway settings were changed: by whom,
 * when, from which IP, and which gateways and fields (with how each changed).
 * It carries no setting value and no secret (PaymentGatewaySettingsChangeNotifier,
 * .ai/rules/admin-env-overrides.md "Security").
 *
 * `$array`: `changes` (`<gateway>.<field>` or `enabled_source` => set|changed|
 * removed|enabled|disabled), `actor_name`, `actor_email`, `changed_at`
 * (formatted), `ip`, `link`.
 */
class PaymentGatewaySettingsChangedMail extends LocalizedMail
{
    public function build(): static
    {
        $this->locale(StoreLocale::deliveryLocale(locale: $this->locale));
        $locale = $this->locale;
        $fields = [];

        foreach ($this->array['changes'] as $key => $kind) {
            $fields[] = __('delivery.mail.payment_gateway_changed.field_change', [
                'field' => $this->fieldName((string) $key, $locale),
                'change' => __('delivery.mail.payment_gateway_changed.kinds.'.$kind, [], $locale),
            ], $locale);
        }

        $subject = __('delivery.mail.payment_gateway_changed.subject', ['shop' => __('messages.brand_name', [], $locale)], $locale);

        return $this->view('shop.emails.payment_gateway_settings_changed')
            ->text('shop.emails.plain.payment_gateway_settings_changed')
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

    /** "bKash: app secret", "bKash", or the enabled-list source; gateway names are brands and stay as they are. */
    private function fieldName(string $key, ?string $locale): string
    {
        if ($key === 'enabled_source') {
            return __('delivery.mail.payment_gateway_changed.enabled_source', [], $locale);
        }

        [$gateway, $field] = array_pad(explode('.', $key, 2), 2, '');
        $name = StorePaymentGateways::gateways()[$gateway]['name'] ?? $gateway;
        $name = (string) $name;

        // A switch reads "bKash (enabled)": the kind already says what happened.
        if ($field === StorePaymentGateways::ENABLED) {
            return $name;
        }

        return __('delivery.mail.payment_gateway_changed.gateway_field', [
            'gateway' => $name,
            'field' => __('delivery.mail.payment_gateway_changed.fields.'.$field, [], $locale),
        ], $locale);
    }
}
