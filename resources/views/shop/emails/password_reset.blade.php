@php($mailLocale = $array['locale'])
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"></head>
<body>
    <h1>{{ __('storefront.auth.password_reset_subject', [], $mailLocale) }}</h1>
    <p>{{ __('delivery.mail.reset_intro', [], $mailLocale) }}</p>
    <p><a dir="ltr" href="{{ $array['link'] }}">{{ __('delivery.mail.reset_action', [], $mailLocale) }}</a></p>
    <p>{{ __('delivery.mail.reset_ignore', [], $mailLocale) }}</p>
</body>
</html>
