@extends('frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ isset($payment) ? translate('Edit Payment') : translate('Add Payment') }}</h1>
            </div>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 fw-700 text-dark">{{ translate('Payment Information') }}</h5>
        </div>
        <div class="card-body">
            <form class="form-default" role="form" action="{{ isset($payment) ? route('payments.update', ['id' => $payment->id ]) : route('payments.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf


                <!-- Customer Name -->
                <div class="form-group" id="customer">
                    <label for="customer_name" class="fs-12 fw-700 text-soft-dark">{{ translate('Customer Name') }}</label>

                    <select class="form-control aiz-selectpicker" name="customer_id" id="customer_id" data-live-search="true">
                        <option value="">{{ translate('Select customer') }}</option>
                        @foreach ($customers as $customer)
                            @php
                                if(preg_match('/[\p{Arabic}]/u', $customer->name) && (preg_match('/[\p{Arabic}]/u', $customer->company_name) || $customer->company_name == 'none')){
                                    $option_text = $customer->email;
                                }elseif(preg_match('/[\p{Arabic}]/u', $customer->name)){
                                    $option_text = $customer->company_name . ' | ' . $customer->email;
                                }else{
                                    $option_text = $customer->name . ' | ' . $customer->email;
                                }
                            @endphp
                            <option value="{{ $customer->id }}" data-otajer-id="{{ $customer->AccSysID }}" @if(isset($payment) && $payment->customer_id == $customer->id) selected @endif>{{ $option_text}}</option>
                        @endforeach
                    </select>
                    @if ($errors->has('customer_id'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('customer_id') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Customre Otajer ID -->
                <div class="form-group">
                    <label for="customer_otajer_id" class="fs-12 fw-700 text-soft-dark">{{ translate('Customer Account ID') }}</label>
                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}" id="customer_otajer_id"
                        value="{{ isset($payment) ? $payment->customer->AccSysID : null }}" placeholder="{{ translate('Customer Otajer ID (optional)') }}" name="customer_otajer_id">
                    @if ($errors->has('customer_otajer_id'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('customer_otajer_id') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label for="amount" class="fs-12 fw-700 text-soft-dark">{{ translate('Amount') }}</label>
                    <input type="number" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ isset($payment) ? $payment->amount : null }}" placeholder="{{ translate('Amount') }}" name="amount">
                    @if ($errors->has('amount'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('amount') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Invoice Code -->
                <div class="form-group">
                    <label for="invoice_code" class="fs-12 fw-700 text-soft-dark">{{ translate('Invoice Code') }}</label>
                    <input type="number" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ isset($payment) ? $payment->invoice_code : null }}" placeholder="{{ translate('Invoice Code') }}" name="invoice_code">
                    @if ($errors->has('invoice_code'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('invoice_code') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label for="notes" class="fs-12 fw-700 text-soft-dark">{{ translate('Notes') }}</label>
                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ isset($payment) ? $payment->notes : null }}" placeholder="{{ translate('Notes') }}" name="notes">
                    @if ($errors->has('notes'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('notes') }}</strong>
                        </span>
                    @endif
                </div>

                <div class="mb-4 mt-4">
                    <button type="submit" href="javascript:void(0)"
                        class="btn btn-primary btn-block fw-600 rounded-4">{{ isset($payment) ? translate('Edit Payment') : translate('Add Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // to change account number after selecting a customer
        document.getElementById('customer_id').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var accSysID = selectedOption.getAttribute('data-otajer-id');
            document.getElementById('customer_otajer_id').value = accSysID;
        });

        // to change the cutomers select after entering an account number
        document.getElementById('customer_otajer_id').addEventListener('change', function() {
            var otajerID = this.value;
            var customerSelect = document.getElementById('customer_id');
            var options = customerSelect.options;

            for (var i = 0; i < options.length; i++) {
                var accSysID = options[i].getAttribute('data-otajer-id');

                if (accSysID === otajerID) {

                    options[i].selected = true;
                    var optionText = options[i].textContent.trim();
                    var filterOptionInnerInner = document.querySelector('.filter-option-inner-inner');

                    if (filterOptionInnerInner) {
                        filterOptionInnerInner.textContent = optionText;
                    }
                    break;
                }
            }
        });
    });
</script>
