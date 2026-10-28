@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}"><head><meta charset="utf-8"></head><body>
@php
	echo $array['content'];
@endphp
</body></html>
