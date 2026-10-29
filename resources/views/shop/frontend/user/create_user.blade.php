@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.navigation.add_customer') }}</h1>
            </div>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 fw-700 text-dark">{{ __('storefront.common.customer_information') }}</h5>
        </div>
        <div class="card-body">
            <form class="form-default" role="form" action="{{ route('store_customer') }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                <x-required-legend />

                <!-- Name -->
                <div class="form-group">
                    <label for="create_customer_name" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.full_name') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_name" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                        value="{{ old('name') }}" placeholder="{{ __('storefront.common.full_name') }}" name="name" maxlength="191" required>
                    @if ($errors->has('name'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('name') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Company Name -->
                <div class="form-group">
                    <label for="create_customer_company_name" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.company_name') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_company_name"
                        class="form-control rounded-0{{ $errors->has('company_name') ? ' is-invalid' : '' }}"
                        value="{{ old('company_name') }}" placeholder="{{ __('storefront.common.company_name') }}"
                        name="company_name" maxlength="191" required>
                    @if ($errors->has('company_name'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_name') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Email or Phone -->
                @if (addon_is_activated('otp_system'))
                    <div class="form-group phone-form-group mb-1">
                        <label for="phone-code" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.phone') }}<x-required-mark /></label>
                        <input type="tel" id="phone-code"
                            class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                            value="{{ old('phone') }}" placeholder="" name="phone" maxlength="20" autocomplete="off" required>
                    </div>

                    <input type="hidden" name="country_code" value="">

                    {{-- Hidden until "Use Email Instead", so neither marked nor `required`, as on the registration forms (guide §44). --}}
                    <div class="form-group email-form-group mb-1 d-none">
                        <label for="create_customer_otp_email" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.email') }}</label>
                        <input type="email" id="create_customer_otp_email"
                            class="form-control rounded-0 {{ $errors->has('email') ? ' is-invalid' : '' }}"
                            value="{{ old('email') }}" placeholder="{{ __('storefront.auth.email') }}" name="email" maxlength="191"
                            autocomplete="off">
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group text-right">
                        <button class="btn btn-link p-0 text-primary" type="button"
                            onclick="toggleEmailPhone(this)"><i>*{{ __('storefront.auth.use_email_instead') }}</i></button>
                    </div>
                @else
                    <div class="form-group">
                        <label for="create_customer_email" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.email') }}<x-required-mark /></label>
                        <input type="email" id="create_customer_email"
                            class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}"
                            value="{{ old('email') }}" placeholder="{{ __('storefront.auth.email') }}" name="email" maxlength="191" required>
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                    </div>
                @endif


                <div class="form-group">
                    <label for="create_customer_phone" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.phone') }}<x-required-mark /></label>
                    <input type="tel" id="create_customer_phone" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                        value="{{ old('phone') }}" placeholder="{{ __('storefront.auth.phone') }}" name="phone" maxlength="20" required>
                    @if ($errors->has('phone'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('phone') }}</strong>
                        </span>
                    @endif
                </div>


                <div class="form-group">
                    <label for="create_customer_country" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.country') }}<x-required-mark /></label>
                    <select id="create_customer_country" class="form-control aiz-selectpicker" name="country" required>
                        @foreach ($countries as $country)
                            <option value="{{ $country->name }}" @if ($country->name == old('country')) selected @endif>
                                {{ $country->name }}</option>
                        @endforeach
                    </select>

                    @if ($errors->has('country'))
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $errors->first('country') }}</strong>
                        </span>
                    @endif
                </div>


                <!-- Tax Number -->
                <div class="form-group">
                    <label for="create_customer_tax_number" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.tax_number') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_tax_number"
                        class="form-control rounded-0{{ $errors->has('tax_number') ? ' is-invalid' : '' }}"
                        value="{{ old('tax_number') }}" placeholder="{{ __('storefront.common.tax_number') }}" name="tax_number" maxlength="100" required>
                    @if ($errors->has('tax_number'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('tax_number') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Bank Account Number -->
                <div class="form-group">
                    <label for="create_customer_bank_account_number"
                        class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.bank_account_number') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_bank_account_number"
                        class="form-control rounded-0{{ $errors->has('bank_account_number') ? ' is-invalid' : '' }}"
                        value="{{ old('bank_account_number') }}" placeholder="{{ __('storefront.auth.bank_account_number') }}"
                        name="bank_account_number" maxlength="100" required>
                    @if ($errors->has('bank_account_number'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('bank_account_number') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- Company Address -->
                <div class="form-group">
                    <label for="create_customer_company_address"
                        class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.company_address') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_company_address"
                        class="form-control rounded-0{{ $errors->has('company_address') ? ' is-invalid' : '' }}"
                        value="{{ old('company_address') }}" placeholder="{{ __('storefront.common.company_address') }}"
                        name="company_address" maxlength="191" required>
                    @if ($errors->has('company_address'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_address') }}</strong>
                        </span>
                    @endif
                </div>


                <!-- Company Shipping Address -->
                <div class="form-group">
                    <label for="create_customer_shipping_address"
                        class="fs-12 fw-700 text-soft-dark">{{ __('storefront.checkout.shipping_address') }}<x-required-mark /></label>
                    <input type="text" id="create_customer_shipping_address"
                        class="form-control rounded-0{{ $errors->has('shipping_address') ? ' is-invalid' : '' }}"
                        value="{{ old('shipping_address') }}" placeholder="{{ __('storefront.checkout.shipping_address') }}"
                        name="shipping_address" maxlength="255" required>
                    @if ($errors->has('shipping_address'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('shipping_address') }}</strong>
                        </span>
                    @endif
                </div>


                <div class="form-group">
                    <label for="create_customer_trade_license"
                        class="fs-14 fw-700 text-soft-dark">{{ __('storefront.common.trade_license') }}<x-required-mark /></label>
                    <input type="file" id="create_customer_trade_license"
                        class="form-control rounded-0{{ $errors->has('trade_license') ? ' is-invalid' : '' }}"
                        value="{{ old('trade_license') }}" placeholder="{{ __('storefront.upload.upload_trade_license') }}"
                        name="trade_license" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
                    @if ($errors->has('trade_license'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('trade_license') }}</strong>
                        </span>
                    @endif
                </div>






                <!-- password -->
                <div class="form-group">
                    <label for="create_customer_password" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.password') }}<x-required-mark /></label>
                    <input type="password" id="create_customer_password"
                        class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}"
                        placeholder="{{ __('storefront.auth.password') }}" name="password" minlength="6" autocomplete="new-password" required>
                    <div class="text-right mt-1">
                        <span
                            class="fs-12 fw-400 text-gray-dark">{{ __('storefront.auth.password_must_contain_at_least_6_digits') }}</span>
                    </div>
                    @if ($errors->has('password'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('password') }}</strong>
                        </span>
                    @endif
                </div>

                <!-- password Confirm -->
                <div class="form-group">
                    <label for="create_customer_password_confirmation"
                        class="fs-12 fw-700 text-soft-dark">{{ __('storefront.auth.confirm_password') }}<x-required-mark /></label>
                    <input type="password" id="create_customer_password_confirmation" class="form-control rounded-0"
                        placeholder="{{ __('storefront.auth.confirm_password') }}" name="password_confirmation" minlength="6" autocomplete="new-password" required>
                </div>

                <!-- Recaptcha -->
                @if (get_setting('google_recaptcha') == 1)
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}">
                        </div>
                        @error('g-recaptcha-response')
                            <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                @endif

                <div class="mb-4 mt-4">
                    <button type="submit" href="javascript:void(0)"
                        class="btn btn-primary btn-block fw-600 rounded-4">{{ __('storefront.common.add_customer') }}</button>

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

        if (input) {
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

        }

        function toggleEmailPhone(el) {
            if (isPhoneShown) {
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                isPhoneShown = false;
                $(el).html(('<i>*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_phone_number_instead')) }} + '</i>'));
            } else {
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                isPhoneShown = true;
                $(el).html(('*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_email_instead')) }}));
            }
        }
    </script>
@endsection
