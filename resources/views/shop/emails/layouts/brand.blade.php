{{--
    Direction A email shell (palette and decisions in .ai/rules/views.md).
    Inline CSS only and web-safe fonts: mail clients ignore web fonts and
    external stylesheets, and the page loads nothing from a third party. The
    brand red is #C4170C on warm paper (#FAF7F4) and ink (#1F1A17) neutrals; the
    brand name is the placeholder messages.brand_name. A view @extends this,
    sets $mailLocale the way the old templates did, and fills the "content"
    section (and optionally "title").
--}}
@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
    $mailDir = $mailLocale === 'ar' ? 'rtl' : 'ltr';
    $mailAlign = $mailLocale === 'ar' ? 'right' : 'left';
    $mailBrand = __('messages.brand_name', [], $mailLocale);
    $mailLogo = get_setting('header_logo');
    $mailFont = "Tahoma, 'Segoe UI', Arial, Helvetica, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $mailDir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', $mailBrand)</title>
</head>
<body style="margin:0;padding:0;background-color:#faf7f4;color:#1f1a17;font-family:{!! $mailFont !!};font-size:15px;line-height:1.6;">
<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#faf7f4" style="background-color:#faf7f4;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="600" border="0" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;">
                <tr>
                    <td bgcolor="#ffffff" dir="{{ $mailDir }}" style="background-color:#ffffff;border-top:4px solid #c4170c;border-bottom:1px solid #ece5df;padding:18px 28px;text-align:{{ $mailAlign }};">
                        <a href="{{ route('home') }}" style="color:#1f1a17;text-decoration:none;font-family:{!! $mailFont !!};">
                            @if ($mailLogo != null)
                                <img src="{{ uploaded_asset($mailLogo) }}" alt="" height="36" style="height:36px;border:0;vertical-align:middle;">
                            @else
                                <img src="{{ static_asset('assets/img/33logoqr.png') }}" alt="" height="36" style="height:36px;border:0;vertical-align:middle;">
                            @endif
                            <span style="font-size:18px;font-weight:bold;color:#1f1a17;vertical-align:middle;padding-{{ $mailLocale === 'ar' ? 'right' : 'left' }}:8px;">{{ $mailBrand }}</span>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#ffffff" dir="{{ $mailDir }}" style="background-color:#ffffff;padding:28px;text-align:{{ $mailAlign }};font-family:{!! $mailFont !!};font-size:15px;line-height:1.6;color:#1f1a17;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td dir="{{ $mailDir }}" style="padding:18px 28px;text-align:{{ $mailAlign }};font-family:{!! $mailFont !!};font-size:12px;line-height:1.6;color:#6b625c;">
                        {{ $mailBrand }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
