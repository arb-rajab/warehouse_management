@extends('frontend.layouts.user_panel')

@section('panel_content')
    <!-- Payment id -->
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ translate('Payment id') }}: {{ $payment->receipt_code }}</h1>
            </div>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="card rounded-0 shadow-none border mb-4">
        <div class="card-header border-bottom-0">
            <h5 class="fs-16 fw-700 text-dark mb-0">{{ translate('Payment Summary') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">

                <div class="col-lg-6">
                    <table class="table-borderless table">
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Receipt Code') }}:</td>
                            <td>{{ $payment->receipt_code }}</td>
                        </tr>

                        <tr>
                            <td class="w-50 fw-600">{{ translate('Invoice Code') }}:</td>
                            <td>{{ $payment->invoice_code }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Customer Account Number') }}:</td>
                            <td>{{ $payment->customer?->AccSysID }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Customer Name') }}:</td>
                            <td>{{ $payment->customer?->name }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Representative Name') }}:</td>
                            <td>{{ $payment->rep?->name }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Representative Serial') }}:</td>
                            <td>{{ $payment->rep?->rep_serial }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Amount') }}:</td>
                            <td>{{ $payment->amount }}</td>
                        </tr>

                    </table>
                </div>
                <div class="col-lg-6">
                    <table class="table-borderless table">
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Payment Date') }}:</td>
                            <td>{{ $payment->date }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Payment Status') }}:</td>
                            <td>{{ $payment->status }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
