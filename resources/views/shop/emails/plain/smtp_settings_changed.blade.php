{!! $subjectLine !!}

{!! __('delivery.mail.smtp_changed.intro', ['shop' => __('messages.brand_name', [], $mailLocale)], $mailLocale) !!}

{!! __('delivery.mail.smtp_changed.changed_by', [], $mailLocale) !!}: {!! $actorName !!} ({!! $actorEmail !!})
{!! __('delivery.mail.smtp_changed.when', [], $mailLocale) !!}: {!! $changedAt !!}
{!! __('delivery.mail.smtp_changed.ip', [], $mailLocale) !!}: {!! $ip !!}
{!! __('delivery.mail.smtp_changed.fields_label', [], $mailLocale) !!}: {!! implode(__('delivery.mail.smtp_changed.separator', [], $mailLocale), $fields) !!}

{!! __('delivery.mail.smtp_changed.no_values', [], $mailLocale) !!}

{!! __('delivery.mail.smtp_changed.advice', [], $mailLocale) !!}

{!! __('delivery.mail.smtp_changed.action', [], $mailLocale) !!}:
{!! $link !!}
