@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
@extends('shop.emails.layouts.brand')

@section('content')
    <p style="margin:0 0 14px;">{{ $content }}</p>
    @include('shop.emails.partials.button', ['href' => $link, 'label' => __('storefront.product.change_bid', [], $mailLocale)])
@endsection
