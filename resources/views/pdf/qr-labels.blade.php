<!doctype html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @if (app()->isLocale('ar'))
            @font-face {
                font-family: 'NotoNaskhArabic';
                src: url('{{ resource_path('fonts/NotoNaskhArabic-Regular.ttf') }}');
                font-weight: normal;
                font-style: normal;
            }
        @endif

        @page {
            size: {{ $qrWidth }}px {{ $qrHeight }}px;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: sans-serif;
        }

        .label {
            width: 100%;
            box-sizing: border-box;
            padding: 8px;
            text-align: center;
        }

        .label img {
            width: 100%;
            height: auto;
        }

        .location {
            margin-top: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        .description {
            margin-top: 2px;
            font-size: 10px;
            color: #555;
        }

        @if (app()->isLocale('ar'))
            .description {
                font-family: 'NotoNaskhArabic', sans-serif;
                direction: rtl;
                text-align: right;
            }
        @endif
    </style>
</head>
<body>
    @foreach ($labels as $entry)
        <div class="label" @unless ($loop->last) style="page-break-after: always;" @endunless>
            <img src="{{ $entry['qrImage'] }}" width="{{ $qrWidth }}" height="{{ $qrHeight }}" alt="{{ $entry['label'] }}">
            <div class="location">{{ $entry['label'] }}</div>
            <div class="description">{{ $entry['description'] }}</div>
        </div>
    @endforeach
</body>
</html>
