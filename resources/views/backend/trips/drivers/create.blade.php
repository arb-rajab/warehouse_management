@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h5 class="mb-0 h6">{{translate('Add Driver')}}</h5>
    </div>

    <!-- Basic Info -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 text-dark">{{ translate('Driver Information') }}</h5>
        </div>
        <div class="card-body">
            <form class="form-default" role="form" action="{{ route('drivers.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf

                <!-- Name -->
                <div class="form-group">
                    <label for="name" class="fs-12 fw-700 text-soft-dark required">{{ translate('Full Name') }}</label>
                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ old('name') }}" placeholder="{{ translate('Full Name') }}" name="name" required>
                    @if ($errors->has('name'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('name') }}</strong>
                        </span>
                    @endif
                </div>

                {{-- <!-- Company Name -->
                <div class="form-group">
                    <label for="company_name" class="fs-12 fw-700 text-soft-dark">{{ translate('Company Name') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('company_name') ? ' is-invalid' : '' }}"
                        value="{{ old('company_name') }}" placeholder="{{ translate('Company name') }}"
                        name="company_name">
                    @if ($errors->has('company_name'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_name') }}</strong>
                        </span>
                    @endif
                </div> --}}

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="fs-12 fw-700 text-soft-dark required">{{ translate('Email') }}</label>
                    <input type="email"
                        class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}"
                        value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email" required>
                    @if ($errors->has('email'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('email') }}</strong>
                        </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="phone" class="fs-12 fw-700 text-soft-dark required">{{ translate('phone') }}</label>
                    <input type="tel" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                        value="{{ old('phone') }}" placeholder="{{ translate('phone') }}" name="phone" required>
                    @if ($errors->has('phone'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('phone') }}</strong>
                        </span>
                    @endif
                </div>

                <div class="form-group">
                    <label for="country" class="fs-12 fw-700 text-soft-dark required">{{ translate('Country') }}</label>
                    <select class="form-control aiz-selectpicker" name="country" required>
                        @foreach ($countries as $country)
                            <option value="{{ $country->name }}" @if ($country->name == old('country')) selected @endif>
                                {{ $country->name }}</option>
                        @endforeach
                    </select>

                    @if ($errors->has('country'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('country') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Tax Number -->
                {{-- <div class="form-group">
                    <label for="tax_number" class="fs-12 fw-700 text-soft-dark">{{ translate('Tax Number') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('tax_number') ? ' is-invalid' : '' }}"
                        value="{{ old('tax_number') }}" placeholder="{{ translate('Tax Number') }}" name="tax_number">
                    @if ($errors->has('tax_number'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('tax_number') }}</strong>
                        </span>
                    @endif
                </div> --}}

                <!-- Bank Account Number -->
                {{-- <div class="form-group">
                    <label for="bank_account_number"
                        class="fs-12 fw-700 text-soft-dark">{{ translate('Bank Account Number') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('bank_account_number') ? ' is-invalid' : '' }}"
                        value="{{ old('bank_account_number') }}" placeholder="{{ translate('Bank Account Number') }}"
                        name="bank_account_number">
                    @if ($errors->has('bank_account_number'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('bank_account_number') }}</strong>
                        </span>
                    @endif
                </div> --}}

                <!-- Company Address -->
                {{-- <div class="form-group">
                    <label for="company_address"
                        class="fs-12 fw-700 text-soft-dark">{{ translate('Company Address') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('company_address') ? ' is-invalid' : '' }}"
                        value="{{ old('company_address') }}" placeholder="{{ translate('Company Address') }}"
                        name="company_address">
                    @if ($errors->has('company_address'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_address') }}</strong>
                        </span>
                    @endif
                </div> --}}


                <!-- Company Shipping Address -->
                {{-- <div class="form-group">
                    <label for="shipping_address"
                        class="fs-12 fw-700 text-soft-dark">{{ translate('Shipping Address') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('shipping_address') ? ' is-invalid' : '' }}"
                        value="{{ old('shipping_address') }}" placeholder="{{ translate('Shipping Address') }}"
                        name="shipping_address">
                    @if ($errors->has('shipping_address'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('shipping_address') }}</strong>
                        </span>
                    @endif
                </div> --}}

                <!-- password -->
                <div class="form-group">
                    <label for="password" class="fs-12 fw-700 text-soft-dark required">{{ translate('Password') }}</label>
                    <input type="password"
                        class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}"
                        placeholder="{{ translate('Password') }}" name="password" required>
                    <div class="text-right mt-1">
                        <span
                            class="fs-12 fw-400 text-gray-dark">{{ translate('Password must contain at least 6 digits') }}</span>
                    </div>
                    @if ($errors->has('password'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('password') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- password Confirm -->
                <div class="form-group">
                    <label for="password_confirmation"
                        class="fs-12 fw-700 text-soft-dark required">{{ translate('Confirm Password') }}</label>
                    <input type="password" class="form-control rounded-0"
                        placeholder="{{ translate('Confirm Password') }}" name="password_confirmation" required>
                </div>

                <!-- Recaptcha -->
                @if (get_setting('google_recaptcha') == 1)
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}">
                        </div>
                    </div>
                @endif

                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('script')

@endsection
