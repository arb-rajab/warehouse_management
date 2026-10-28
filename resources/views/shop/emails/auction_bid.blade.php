@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}"><head><meta charset="utf-8"></head><body>
<p>{{ $content }}</p>
<a class="btn btn-primary btn-md" href="{{ $link }}">{{ __('storefront.product.change_bid', [], $mailLocale) }}</a>
</body></html>
