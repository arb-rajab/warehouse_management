@php($mailLocale = $array['locale'])
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"></head>
<body>
    <h1>{{ $array['subject'] }}</h1>
    <p>{{ $array['content'] }}</p>
    <a dir="ltr" href="{{ $array['link'] }}">{{ __('delivery.mail.see_details', [], $mailLocale) }}</a>
</body>
</html>
