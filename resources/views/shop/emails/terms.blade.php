@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
@extends('shop.emails.layouts.brand')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ $array['subject'] }}</h1>
    <p style="margin:0 0 14px;">{{ $array['content'] }}</p>
    @if(trim((string) get_setting('terms')) !== '')
    <div style="margin:0 0 16px;padding:14px 16px;background-color:#faf7f4;border:1px solid #ece5df;border-radius:10px;font-size:13px;line-height:1.6;color:#3d3530;white-space:pre-wrap;">{{ trim((string) get_setting('terms')) }}</div>
    @endif
    @if(!empty($array['link']))
        @include('shop.emails.partials.button', ['href' => $array['link'], 'label' => __('storefront.common.agree', [], $mailLocale)])
    @endif
@endsection
