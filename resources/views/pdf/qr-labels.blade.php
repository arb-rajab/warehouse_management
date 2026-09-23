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
            display: block;
            margin: 0 auto;
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
    @php
        // The QR image is square, so scaling it to fill the full page width (as
        // this template did before one-page-per-label existed) leaves however
        // much of $qrHeight is left over below it for the .location/.description
        // text — for a $qrWidth close to $qrHeight (e.g. 900x950) that's only a
        // few px, not enough for even one line, and dompdf silently overflows
        // the label onto a second page instead of clipping it. $reservedTextHeight
        // is a fixed budget (padding + the two short text lines' height +
        // margins, see .label/.location/.description below) that's always kept
        // clear beneath the QR, shrinking the QR itself when needed — mirroring
        // the same guaranteed-minimum-text-zone approach BuildsQrLabels::
        // qrLabelImage() already uses for the single-cell/product SVG export.
        $labelPadding = 8;
        $reservedTextHeight = 64;
        $qrDisplaySize = max(40, (int) min(
            $qrWidth - $labelPadding * 2,
            $qrHeight - $labelPadding * 2 - $reservedTextHeight,
        ));
    @endphp
    @foreach ($labels as $entry)
        <div class="label" @unless ($loop->last) style="page-break-after: always;" @endunless>
            <img src="{{ $entry['qrImage'] }}" width="{{ $qrDisplaySize }}" height="{{ $qrDisplaySize }}" alt="{{ $entry['label'] }}">
            <div class="location">{{ $entry['label'] }}</div>
            <div class="description">{{ $entry['description'] }}</div>
        </div>
    @endforeach
</body>
</html>
