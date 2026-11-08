@php($mailLocale = $array['locale'])
@extends('shop.emails.layouts.brand')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ $array['subject'] }}</h1>
    <p style="margin:0 0 14px;">{{ $array['content'] }}</p>
    @include('shop.emails.partials.button', ['href' => $array['link'], 'label' => __('delivery.mail.see_details', [], $mailLocale)])
@endsection
