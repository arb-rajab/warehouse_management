@php($mailLocale = $array['locale'])
@extends('shop.emails.layouts.brand')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ __('storefront.auth.password_reset_subject', [], $mailLocale) }}</h1>
    <p style="margin:0 0 14px;">{{ __('delivery.mail.reset_intro', [], $mailLocale) }}</p>
    @include('shop.emails.partials.button', ['href' => $array['link'], 'label' => __('delivery.mail.reset_action', [], $mailLocale)])
    <p style="margin:0 0 14px;font-size:13px;color:#6b625c;">{{ __('delivery.mail.reset_ignore', [], $mailLocale) }}</p>
@endsection
