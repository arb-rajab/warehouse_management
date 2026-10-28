@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}"><head><meta charset="utf-8"></head><body>
<h1>{{ __('delivery.mail.ticket', [], $mailLocale) }}</h1>
<p>{{ $content }}</p>
<p><b>{{ __('delivery.mail.sender', [], $mailLocale) }}: </b>{{ $sender }}</p>
<p>
	<b>{{ __('delivery.mail.details', [], $mailLocale) }}:</b>
	<br>
	{!! clean_html($details) !!}
</p>
<a class="btn btn-primary btn-md" href="{{ $link }}">{{ __('delivery.mail.see_ticket', [], $mailLocale) }}</a>

</body></html>
