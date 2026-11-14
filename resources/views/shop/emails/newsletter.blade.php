@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
@extends('shop.emails.layouts.brand')

{{-- The content is admin-written, sanitised HTML (NewsletterController), printed as it is. --}}
@section('content')
@php
	echo $array['content'];
@endphp
@endsection
