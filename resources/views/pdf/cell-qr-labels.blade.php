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

        body {
            margin: 0;
            font-family: sans-serif;
        }

        .label {
            display: inline-block;
            width: 30%;
            box-sizing: border-box;
            margin: 1%;
            padding: 8px;
            text-align: center;
            vertical-align: top;
            border: 1px dashed #999;
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
        <div class="label">
            <img src="{{ $entry['qrImage'] }}" width="200" height="200" alt="{{ $entry['label'] }}">
            <div class="location">{{ $entry['label'] }}</div>
            <div class="description">{{ $entry['description'] }}</div>
        </div>
    @endforeach
</body>
</html>
