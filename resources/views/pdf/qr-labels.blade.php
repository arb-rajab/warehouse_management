<!doctype html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {{ $qrWidth }}px {{ $qrHeight }}px;
            margin: 0;
        }

        body {
            margin: 0;
        }

        .label {
            text-align: center;
        }

        .label img {
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    {{-- Each entry's labelImage is the exact same SVG cellQrLabelImage() produces
         for the single-cell download (see BuildsCellQrLabels::cellQrLabels()) —
         QR plus the expanded slot label, no separate description line — so this
         sheet can't drift from what the single-cell export renders. That SVG
         already sizes/pads/shrinks itself to fit within $qrWidth/$qrHeight, so
         the <img> is embedded at its own intrinsic size rather than re-scaled
         here. --}}
    @foreach ($labels as $entry)
        <div class="label" @unless ($loop->last) style="page-break-after: always;" @endunless>
            <img src="{{ $entry['labelImage'] }}" alt="{{ $entry['label'] }}">
        </div>
    @endforeach
</body>
</html>
