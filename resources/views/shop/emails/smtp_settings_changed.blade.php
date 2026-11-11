@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
@extends('shop.emails.layouts.brand')

{{-- App\Mail\SmtpSettingsChangedMail: who changed the SMTP settings, when, from where and which fields. Never a value. --}}
@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ $subjectLine }}</h1>
    <p style="margin:0 0 14px;">{{ __('delivery.mail.smtp_changed.intro', ['shop' => __('messages.brand_name', [], $mailLocale)], $mailLocale) }}</p>
    <p style="margin:0 0 6px;"><b>{{ __('delivery.mail.smtp_changed.changed_by', [], $mailLocale) }}:</b> {{ $actorName }} ({{ $actorEmail }})</p>
    <p style="margin:0 0 6px;"><b>{{ __('delivery.mail.smtp_changed.when', [], $mailLocale) }}:</b> {{ $changedAt }}</p>
    <p style="margin:0 0 6px;"><b>{{ __('delivery.mail.smtp_changed.ip', [], $mailLocale) }}:</b> <span dir="ltr">{{ $ip }}</span></p>
    <p style="margin:0 0 14px;"><b>{{ __('delivery.mail.smtp_changed.fields_label', [], $mailLocale) }}:</b> {{ implode(__('delivery.mail.smtp_changed.separator', [], $mailLocale), $fields) }}</p>
    <p style="margin:0 0 14px;">{{ __('delivery.mail.smtp_changed.no_values', [], $mailLocale) }}</p>
    <p style="margin:0 0 14px;">{{ __('delivery.mail.smtp_changed.advice', [], $mailLocale) }}</p>
    @include('shop.emails.partials.button', ['href' => $link, 'label' => __('delivery.mail.smtp_changed.action', [], $mailLocale)])
@endsection
