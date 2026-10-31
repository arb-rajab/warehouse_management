@extends('shop.frontend.layouts.app')

@section('content')
    <section class="gry-bg py-6">
        <div class="profile">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9 col-lg-10 mx-auto">
                        <div class="card shadow-none rounded-0 border">
                            <div class="row">
                                <!-- Left Side -->
                                <div class="col-lg-6 col-md-7 p-4 p-lg-5">
                                    <!-- Titles -->
                                    <div class="text-center">
                                        <h1 class="fs-20 fs-md-24 fw-700 text-primary">{{ __('storefront.auth.create_an_account')}}</h1>
                                    </div>
                                    <!-- Register form -->
                                    <div class="pt-3 pt-lg-4">
                                        <div class="">
                                            <form id="reg-form" class="form-default" role="form" action="{{ route('register') }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                <x-required-legend />

                                                <!-- Name -->
                                                <div class="form-group">
                                                    <label for="name" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.common.full_name') }}<x-required-mark /></label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name') }}" placeholder="{{  __('storefront.common.full_name') }}" name="name" required maxlength="191">
                                                    @if ($errors->has('name'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('name') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- Company Name -->
                                                <div class="form-group">
                                                    <label for="company_name" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.common.company_name') }}<x-required-mark /></label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('company_name') ? ' is-invalid' : '' }}" value="{{ old('company_name') }}" placeholder="{{  __('storefront.common.company_name') }}" name="company_name" required maxlength="191">
                                                    @if ($errors->has('company_name'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('company_name') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- Email or Phone -->
                                                @if (addon_is_activated('otp_system'))
                                                    <div class="form-group phone-form-group mb-1">
                                                        <label for="phone" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.phone') }}</label>
                                                        <input type="tel" id="phone-code" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="" name="phone" autocomplete="off" maxlength="20">
                                                    </div>

                                                    <div class="form-group email-form-group mb-1 d-none">
                                                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.email') }}</label>
                                                        <input type="email" class="form-control rounded-0 {{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  __('storefront.auth.email') }}" name="email"  autocomplete="off" maxlength="191">
                                                        @if ($errors->has('email'))
                                                            <span class="invalid-feedback" role="alert">
                                                                <strong>{{ $errors->first('email') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="form-group text-right">
                                                        <button class="btn btn-link p-0 text-primary" type="button" onclick="toggleEmailPhone(this)"><i>*{{ __('storefront.auth.use_email_instead') }}</i></button>
                                                    </div>
                                                @else
                                                    <div class="form-group">
                                                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.email') }}<x-required-mark /></label>
                                                        <input type="email" class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  __('storefront.auth.email') }}" name="email" required maxlength="191">
                                                        @if ($errors->has('email'))
                                                            <span class="invalid-feedback" role="alert">
                                                                <strong>{{ $errors->first('email') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif


                                                <div class="form-group">
                                                    <label for="phone" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.phone') }}<x-required-mark /></label>
                                                    <input type="tel" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="{{  __('storefront.auth.phone') }}" name="phone" required maxlength="20">
                                                    @if ($errors->has('phone'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('phone') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>


                                                <div class="form-group">
                                                    <label for="country" class="fs-12 fw-700 text-soft-dark">{{ __('storefront.common.country') }}<x-required-mark /></label>
                                                    <select class="form-control aiz-selectpicker" name="country" required>
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->name }}" @if ($country->name == old('country')) selected @endif>{{ $country->name }}</option>
                                                        @endforeach
                                                    </select>

                                                    @if ($errors->has('country'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('country') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>


                                                {{-- <!-- Tax Number -->
                                                <div class="form-group">
                                                    <label for="tax_number" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.common.tax_number') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('tax_number') ? ' is-invalid' : '' }}" value="{{ old('tax_number') }}" placeholder="{{  __('storefront.common.tax_number') }}" name="tax_number">
                                                    @if ($errors->has('tax_number'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('tax_number') }}</strong>
                                                        </span>
                                                    @endif
                                                </div> --}}

                                                {{-- <!-- Bank Account Number -->
                                                <div class="form-group">
                                                    <label for="bank_account_number" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.bank_account_number') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('bank_account_number') ? ' is-invalid' : '' }}" value="{{ old('bank_account_number') }}" placeholder="{{  __('storefront.auth.bank_account_number') }}" name="bank_account_number">
                                                    @if ($errors->has('bank_account_number'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('bank_account_number') }}</strong>
                                                        </span>
                                                    @endif
                                                </div> --}}

                                                {{-- <!-- Company Address -->
                                                <div class="form-group">
                                                    <label for="company_address" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.common.company_address') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('company_address') ? ' is-invalid' : '' }}" value="{{ old('company_address') }}" placeholder="{{  __('storefront.common.company_address') }}" name="company_address">
                                                    @if ($errors->has('company_address'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('company_address') }}</strong>
                                                        </span>
                                                    @endif
                                                </div> --}}


                                                {{-- <!-- Company Shipping Address -->
                                                <div class="form-group">
                                                    <label for="shipping_address" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.checkout.shipping_address') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('shipping_address') ? ' is-invalid' : '' }}" value="{{ old('shipping_address') }}" placeholder="{{  __('storefront.checkout.shipping_address') }}" name="shipping_address">
                                                    @if ($errors->has('shipping_address'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('shipping_address') }}</strong>
                                                        </span>
                                                    @endif
                                                </div> --}}


                                                {{-- <div class="form-group">
                                                    <label for="trade_license" class="fs-14 fw-700 text-soft-dark">{{  __('storefront.common.trade_license') }}</label>
                                                    <input type="file" class="form-control rounded-0{{ $errors->has('trade_license') ? ' is-invalid' : '' }}" value="{{ old('trade_license') }}" placeholder="{{  __('storefront.upload.upload_trade_license') }}" name="trade_license">
                                                    @if ($errors->has('trade_license'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('trade_license') }}</strong>
                                                        </span>
                                                    @endif
                                                </div> --}}






                                                <!-- password -->
                                                <div class="form-group">
                                                    <label for="password" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.password') }}<x-required-mark /></label>
                                                    <input type="password" class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{  __('storefront.auth.password') }}" name="password" required>
                                                    <div class="text-right mt-1">
                                                        <span class="fs-12 fw-400 text-gray-dark">{{ __('storefront.auth.password_must_contain_at_least_6_digits') }}</span>
                                                    </div>
                                                    @if ($errors->has('password'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('password') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- password Confirm -->
                                                <div class="form-group">
                                                    <label for="password_confirmation" class="fs-12 fw-700 text-soft-dark">{{  __('storefront.auth.confirm_password') }}<x-required-mark /></label>
                                                    <input type="password" class="form-control rounded-0" placeholder="{{  __('storefront.auth.confirm_password') }}" name="password_confirmation" required>
                                                </div>

                                                <!-- Recaptcha -->
                                                @if(get_setting('google_recaptcha') == 1)
                                                    <div class="form-group">
                                                        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                                                        @error('g-recaptcha-response')
                                                            <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                                        @enderror
                                                    </div>
                                                @endif



                                                <!-- terms & conditions modal -->
                                                <div class="modal fade" id="terms_conditions_modal" tabindex="-1" role="dialog" aria-labelledby="account_delete_confirmModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                                        <div class="modal-content pb-2" style="max-height: 90vh; overflow-y: auto;">
                                                            <div class="modal-header d-block py-4">
                                                                <div class="d-flex justify-content-center">
                                                                </div>
                                                                <h4 class="modal-title text-center fw-700" id="personalInfo_enterPassword_modalModalLabel" style="color: #ff0000;">{{ __('storefront.common.terms_conditions')}}</h4>
                                                                <p class="fs-16 fw-600 text-center" style="color: #8d8d8d;">{{ __('storefront.auth.please_read_and_agree_to_terms_and_conditions_to_create_an_account')}}</p>
                                                            </div>

                                                            <div class="modal-body">
                                                                @php
                                                                    $termsPage = \App\Models\Page::query()->where('type', 'terms_conditions_page')->first();
                                                                    $termsContent = $termsPage?->getTranslation('content');
                                                                @endphp
                                                                @if (filled($termsContent))
                                                                    <div class="border p-3" id="terms_conditions_text" style="max-height: 60vh; overflow-y: auto;">{!! $termsContent !!}</div>
                                                                    <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="d-inline-block mt-2 fs-12">{{ __('storefront.common.open_the_terms_in_a_new_page') }}</a>
                                                                @else
                                                                    <p class="text-muted mb-0" id="terms_conditions_text">{{ __('storefront.common.the_terms_and_conditions_are_not_available_yet') }}</p>
                                                                @endif
                                                            </div>

                                                            <div class="modal-footer pb-4">
                                                                <button type="submit" id="submitButton" class="btn btn-primary btn-block fw-600 rounded-4">{{ __('storefront.common.agree') }}</button>
                                                                <button type="button" class="btn btn-secondary btn-block fw-600 rounded-4" data-dismiss="modal">{{ __('storefront.common.cancel')}}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

<!-- end modal -->

                                                <div class="mb-4 mt-4">
                                                    <a href="javascript:void(0)" onclick="terms_conditions_modal()" class="btn btn-primary btn-block fw-600 rounded-4">{{ __('storefront.auth.create_an_account') }}</a>

                                                </div>
                                            </form>

                                            <!-- Social Login -->
                                            @if(get_setting('google_login') == 1 || get_setting('facebook_login') == 1 || get_setting('twitter_login') == 1 || get_setting('apple_login') == 1)
                                                <div class="text-center mb-3">
                                                    <span class="bg-white fs-12 text-gray">{{ __('storefront.common.or_join_with')}}</span>
                                                </div>
                                                <ul class="list-inline social colored text-center mb-4">
                                                    @if (get_setting('facebook_login') == 1)
                                                        <li class="list-inline-item">
                                                            <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                                                                <i class="lab la-facebook-f"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if(get_setting('google_login') == 1)
                                                        <li class="list-inline-item">
                                                            <a href="{{ route('social.login', ['provider' => 'google']) }}" class="google">
                                                                <i class="lab la-google"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if (get_setting('twitter_login') == 1)
                                                        <li class="list-inline-item">
                                                            <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="twitter">
                                                                <i class="lab la-twitter"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if (get_setting('apple_login') == 1)
                                                        <li class="list-inline-item">
                                                            <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="apple">
                                                                <i class="lab la-apple"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                </ul>
                                            @endif
                                        </div>

                                        <!-- Log In -->
                                        <div class="text-center">
                                            <p class="fs-12 text-gray mb-0">{{ __('storefront.auth.already_have_an_account')}}</p>
                                            <a href="{{ route('user.login') }}" class="fs-14 fw-700 animate-underline-primary">{{ __('storefront.auth.log_in')}}</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Side Image -->
                                <div class="col-lg-6 col-md-5 py-3 py-md-0">
                                    <img src="{{ uploaded_asset(get_setting('register_page_image')) }}" alt="" class="img-fit">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection


@section('script')
    @if(get_setting('google_recaptcha') == 1)
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    <script type="text/javascript">

        @if(get_setting('google_recaptcha') == 1)
        // making the CAPTCHA  a required field for form submission
        $(document).ready(function(){
            $("#reg-form").on("submit", function(evt)
            {
                var response = grecaptcha.getResponse();
                if(response.length == 0)
                {
                //reCaptcha not verified
                    alert({{ \Illuminate\Support\Js::from(__('storefront.auth.verify_human')) }});
                    evt.preventDefault();
                    return false;
                }
                //captcha verified
                //do the rest of your validations here
                $("#reg-form").submit();
            });
        });
        @endif

        var isPhoneShown = true,
            countryData = window.intlTelInputGlobals.getCountryData(),
            input = document.querySelector("#phone-code");

        for (var i = 0; i < countryData.length; i++) {
            var country = countryData[i];
            if(country.iso2 == 'bd'){
                country.dialCode = '88';
            }
        }

        intlTelInput(input, {
            separateDialCode: true,
            utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
            onlyCountries: @php echo json_encode(\App\Models\Country::where('status', 1)->pluck('code')->toArray()) @endphp,
            customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                if(selectedCountryData.iso2 == 'bd'){
                    return "01xxxxxxxxx";
                }
                return selectedCountryPlaceholder;
            }
        });

        function toggleEmailPhone(el){
            if(isPhoneShown){
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                isPhoneShown = false;
                $(el).html(('<i>*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_phone_number_instead')) }} + '</i>'));
            }
            else{
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                isPhoneShown = true;
                $(el).html(('*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_email_instead')) }}));
            }
        }
    </script>
<script>
    function terms_conditions_modal()
    {
        jQuery('#terms_conditions_modal').modal('show', {backdrop: 'static'});

    }


</script>

<script>
    $(document).ready(function() {
        // Prevent form submission on Enter key press
        $("form").on("keypress", function(event) {
            if (event.keyCode === 13) {
                event.preventDefault();
                // Show terms and conditions modal
                $("#terms_conditions_modal").modal("show");
            }
        });
    });
</script>

<script>
    $(document).ready(function() {
        $('#submitButton').click(function() {
            $('#terms_conditions_modal').modal('hide');
        });
    });
</script>

@endsection
