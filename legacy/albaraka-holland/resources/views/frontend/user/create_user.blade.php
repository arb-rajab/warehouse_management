@extends('frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ translate('Add Customer') }}</h1>
            </div>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 fw-700 text-dark">{{ translate('Customer Information') }}</h5>
        </div>
        <div class="card-body">
            <form class="form-default" role="form" action="{{ route('store_customer') }}" method="POST"
                enctype="multipart/form-data">
                @csrf

                <!-- Name -->
                <div class="form-group">
                    <label for="name" class="fs-12 fw-700 text-soft-dark">{{ translate('Full Name') }}</label>
                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ old('name') }}" placeholder="{{ translate('Full Name') }}" name="name">
                    @if ($errors->has('name'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('name') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Company Name -->
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
                </div>

                <!-- Email or Phone -->
                @if (addon_is_activated('otp_system'))
                    <div class="form-group phone-form-group mb-1">
                        <label for="phone" class="fs-12 fw-700 text-soft-dark">{{ translate('Phone') }}</label>
                        <input type="tel" id="phone-code"
                            class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                            value="{{ old('phone') }}" placeholder="" name="phone" autocomplete="off">
                    </div>

                    <input type="hidden" name="country_code" value="">

                    <div class="form-group email-form-group mb-1 d-none">
                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{ translate('Email') }}</label>
                        <input type="email"
                            class="form-control rounded-0 {{ $errors->has('email') ? ' is-invalid' : '' }}"
                            value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email"
                            autocomplete="off">
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group text-right">
                        <button class="btn btn-link p-0 text-primary" type="button"
                            onclick="toggleEmailPhone(this)"><i>*{{ translate('Use Email Instead') }}</i></button>
                    </div>
                @else
                    <div class="form-group">
                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{ translate('Email') }}</label>
                        <input type="email"
                            class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}"
                            value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email">
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                    </div>
                @endif


                <div class="form-group">
                    <label for="phone" class="fs-12 fw-700 text-soft-dark">{{ translate('phone') }}</label>
                    <input type="tel" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                        value="{{ old('phone') }}" placeholder="{{ translate('phone') }}" name="phone">
                    @if ($errors->has('phone'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('phone') }}</strong>
                        </span>
                    @endif
                </div>


                <div class="form-group">
                    <label for="country" class="fs-12 fw-700 text-soft-dark">{{ translate('Country') }}</label>
                    <select class="form-control aiz-selectpicker" name="country">
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
                <div class="form-group">
                    <label for="tax_number" class="fs-12 fw-700 text-soft-dark">{{ translate('Tax Number') }}</label>
                    <input type="text"
                        class="form-control rounded-0{{ $errors->has('tax_number') ? ' is-invalid' : '' }}"
                        value="{{ old('tax_number') }}" placeholder="{{ translate('Tax Number') }}" name="tax_number">
                    @if ($errors->has('tax_number'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('tax_number') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Bank Account Number -->
                <div class="form-group">
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
                </div>

                <!-- Company Address -->
                <div class="form-group">
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
                </div>


                <!-- Company Shipping Address -->
                <div class="form-group">
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
                </div>


                <div class="form-group">
                    <label for="trade_license"
                        class="fs-14 fw-700 text-soft-dark">{{ translate('Trade License') }}</label>
                    <input type="file"
                        class="form-control rounded-0{{ $errors->has('trade_license') ? ' is-invalid' : '' }}"
                        value="{{ old('trade_license') }}" placeholder="{{ translate('Upload Trade License') }}"
                        name="trade_license">
                    @if ($errors->has('trade_license'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('trade_license') }}</strong>
                        </span>
                    @endif
                </div>






                <!-- password -->
                <div class="form-group">
                    <label for="password" class="fs-12 fw-700 text-soft-dark">{{ translate('Password') }}</label>
                    <input type="password"
                        class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}"
                        placeholder="{{ translate('Password') }}" name="password">
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
                        class="fs-12 fw-700 text-soft-dark">{{ translate('Confirm Password') }}</label>
                    <input type="password" class="form-control rounded-0"
                        placeholder="{{ translate('Confirm Password') }}" name="password_confirmation">
                </div>

                <!-- Recaptcha -->
                @if (get_setting('google_recaptcha') == 1)
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}">
                        </div>
                    </div>
                @endif

                <div class="mb-4 mt-4">
                    <button type="submit" href="javascript:void(0)"
                        class="btn btn-primary btn-block fw-600 rounded-4">{{ translate('Add customer') }}</button>

                </div>
            </form>
        </div>
    </div>

@endsection


@section('script')
    <script type="text/javascript">
        var isPhoneShown = true,
            countryData = window.intlTelInputGlobals.getCountryData(),
            input = document.querySelector("#phone-code");

        for (var i = 0; i < countryData.length; i++) {
            var country = countryData[i];
            if (country.iso2 == 'bd') {
                country.dialCode = '88';
            }
        }

        var iti = intlTelInput(input, {
            separateDialCode: true,
            utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
            onlyCountries: @php
                echo json_encode(
                    \App\Models\Country::where('status', 1)
                        ->pluck('code')
                        ->toArray(),
                );
            @endphp,
            customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                if (selectedCountryData.iso2 == 'bd') {
                    return "01xxxxxxxxx";
                }
                return selectedCountryPlaceholder;
            }
        });

        var country = iti.getSelectedCountryData();
        $('input[name=country_code]').val(country.dialCode);

        input.addEventListener("countrychange", function(e) {
            // var currentMask = e.currentTarget.placeholder;

            var country = iti.getSelectedCountryData();
            $('input[name=country_code]').val(country.dialCode);

        });

        function toggleEmailPhone(el) {
            if (isPhoneShown) {
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                isPhoneShown = false;
                $(el).html('<i>*{{ translate('Use Phone Number Instead') }}</i>');
            } else {
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                isPhoneShown = true;
                $(el).html('*{{ translate('Use Email Instead') }}');
            }
        }
    </script>
@endsection
