{!! $subjectLine !!}

{!! __('delivery.mail.payment_gateway_changed.intro', ['shop' => __('messages.brand_name', [], $mailLocale)], $mailLocale) !!}

{!! __('delivery.mail.payment_gateway_changed.changed_by', [], $mailLocale) !!}: {!! $actorName !!} ({!! $actorEmail !!})
{!! __('delivery.mail.payment_gateway_changed.when', [], $mailLocale) !!}: {!! $changedAt !!}
{!! __('delivery.mail.payment_gateway_changed.ip', [], $mailLocale) !!}: {!! $ip !!}
{!! __('delivery.mail.payment_gateway_changed.fields_label', [], $mailLocale) !!}: {!! implode(__('delivery.mail.payment_gateway_changed.separator', [], $mailLocale), $fields) !!}

{!! __('delivery.mail.payment_gateway_changed.no_values', [], $mailLocale) !!}

{!! __('delivery.mail.payment_gateway_changed.advice', [], $mailLocale) !!}

{!! __('delivery.mail.payment_gateway_changed.action', [], $mailLocale) !!}:
{!! $link !!}
