@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
@extends('shop.emails.layouts.brand')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ __('delivery.mail.ticket', [], $mailLocale) }}</h1>
    <p style="margin:0 0 14px;">{{ $content }}</p>
    <p style="margin:0 0 14px;"><b>{{ __('delivery.mail.sender', [], $mailLocale) }}:</b> {{ $sender }}</p>
    <p style="margin:0 0 14px;">
        <b>{{ __('delivery.mail.details', [], $mailLocale) }}:</b>
        <br>
        {!! clean_html($details) !!}
    </p>
    @include('shop.emails.partials.button', ['href' => $link, 'label' => __('delivery.mail.see_ticket', [], $mailLocale)])
@endsection
