@php
    $documentLocale = $documentLocale ?? \App\Support\StoreLocale::documentLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $documentLocale }}" dir="{{ $documentLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('storefront.checkout.payment', [], $documentLocale) }}</title>
    <style>
        body {
            font-size: 2rem;
            margin: 0;
            padding: 0;
            font-family: dejavusans, sans-serif;
            direction: {{ $documentLocale === 'ar' ? 'rtl' : 'ltr' }};

            color: #000;
        }

        .container {
            /* padding-left: 7rem;
            padding-right: 7rem; */
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 0.5rem;
            border: 1px solid #eceff4;
            text-align: center;
            font-weight: normal;
        }

        .strong {
            font-weight: bold;
        }

        /* .small {
            font-size: 12px;
        } */

        .gry-color {
            color: #666;
        }

        .key {
            text-align: {{ $documentLocale === 'ar' ? 'right' : 'left' }};
            padding-right: 1rem;
        }
        .value {
            text-align: {{ $documentLocale === 'ar' ? 'right' : 'left' }};
            padding-left: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <table>
            <tr>
                <td class="key strong small gry-color">{{ __('storefront.checkout.receipt_code', [], $documentLocale) }}</td>
                <td class="value strong small gry-color"><span dir="ltr">{{ $payment->receipt_code }}</span></td>
            </tr>
            <tr>
                <td class="key strong small gry-color">{{ __('storefront.checkout.invoice_code', [], $documentLocale) }}</td>
                <td class="value strong small gry-color"><span dir="ltr">{{ $payment->invoice_code }}</span></td>
            </tr>
            <tr>
                <td class="key strong small">{{ __('storefront.common.amount', [], $documentLocale) }}</td>
                <td class="value strong small"><span dir="ltr">{{ $payment->amount }}</span></td>
            </tr>
            <tr>
                <td class="key strong">{{ __('storefront.auth.customer_account_id', [], $documentLocale) }}</td>
                <td class="value strong"><span dir="ltr">{{ !is_null($payment->otajer_id) ? $payment->otajer_id : $payment->customer?->AccSysID }}</span></td>
            </tr>
            <tr>
                <td class="key strong">{{ __('storefront.common.customer_name', [], $documentLocale) }}</td>
                <td class="value strong">{{ $payment->customer?->name }}</td>
            </tr>
            <tr>
                <td class="key strong gry-color">{{ __('storefront.common.representative_serial', [], $documentLocale) }}</td>
                <td class="value strong gry-color"><span dir="ltr">{{ $payment->rep?->rep_serial }}</span></td>
            </tr>
            <tr>
                <td class="key strong">{{ __('storefront.common.representative_name', [], $documentLocale) }}</td>
                <td class="value strong">{{ $payment->rep?->name }}</td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ __('storefront.common.date', [], $documentLocale) }}</td>
                <td class="value gry-color small"><span dir="ltr">{{ \Carbon\Carbon::parse($payment->date)->format('Y-m-d') }}</span></td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ __('storefront.common.notes', [], $documentLocale) }}</td>
                <td class="value gry-color small">{{ $payment->notes }}</td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ __('storefront.common.qr', [], $documentLocale) }}</td>
                <td class="value gry-color small">{!! preg_replace('/<\?xml[^>]*\?>/', '', $qr) !!}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
