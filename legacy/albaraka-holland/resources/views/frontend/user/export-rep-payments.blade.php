<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('Payment') }}</title>
    <style>
        body {
            font-size: 2rem;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;

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
            text-align: left;
            padding-right: 1rem;
        }
        .value {
            text-align: left;
            padding-left: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <table>
            <tr>
                <td class="key strong small gry-color">{{ translate('Receipt Code') }}</td>
                <td class="value strong small gry-color">{{ $payment->receipt_code }}</td>
            </tr>
            <tr>
                <td class="key strong small gry-color">{{ translate('Invoice Code') }}</td>
                <td class="value strong small gry-color">{{ $payment->invoice_code }}</td>
            </tr>
            <tr>
                <td class="key strong small">{{ translate('Amount') }}</td>
                <td class="value strong small">{{ $payment->amount }}</td>
            </tr>
            <tr>
                <td class="key strong">{{ translate('Customer Account ID') }}</td>
                <td class="value strong">{{ !is_null($payment->otajer_id) ? $payment->otajer_id : $payment->customer?->AccSysID}}</td>
            </tr>
            <tr>
                <td class="key strong">{{ translate('Customer Name') }}</td>
                <td class="value strong">{{ $payment->customer?->name }}</td>
            </tr>
            <tr>
                <td class="key strong gry-color">{{ translate('Representative Serial') }}</td>
                <td class="value strong gry-color">{{ $payment->rep?->rep_serial }}</td>
            </tr>
            <tr>
                <td class="key strong">{{ translate('Representative Name') }}</td>
                <td class="value strong">{{ $payment->rep?->name }}</td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ translate('Date') }}</td>
                <td class="value gry-color small">{{ \Carbon\Carbon::parse($payment->date)->format('Y-m-d') }}</td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ translate('Notes') }}</td>
                <td class="value gry-color small">{{ $payment->notes }}</td>
            </tr>
            <tr>
                <td class="key gry-color small">{{ translate('QR') }}</td>
                <td class="value gry-color small">{!! str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $qr) !!}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
