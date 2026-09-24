@extends('frontend.layouts.app')

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
                                        <h1 class="fs-20 fs-md-24 fw-700 text-primary">{{ translate('Create an account')}}</h1>
                                    </div>
                                    <!-- Register form -->
                                    <div class="pt-3 pt-lg-4">
                                        <div class="">
                                            <form id="reg-form" class="form-default" role="form" action="{{ route('register') }}" method="POST">
                                                @csrf

                                                <!-- Name -->
                                                <div class="form-group">
                                                    <label for="name" class="fs-12 fw-700 text-soft-dark">{{  translate('Full Name') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name') }}" placeholder="{{  translate('Full Name') }}" name="name" required>
                                                    @if ($errors->has('name'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('name') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- Email or Phone -->
                                                @if (addon_is_activated('otp_system'))
                                                    <div class="form-group phone-form-group mb-1">
                                                        <label for="phone" class="fs-12 fw-700 text-soft-dark">{{  translate('Phone') }}</label>
                                                        <input type="tel" id="phone-code" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="" name="phone" autocomplete="off">
                                                    </div>

                                                    <input type="hidden" name="country_code" value="">

                                                    <div class="form-group email-form-group mb-1 d-none">
                                                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{  translate('Email') }}</label>
                                                        <input type="email" class="form-control rounded-0 {{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email"  autocomplete="off">
                                                        @if ($errors->has('email'))
                                                            <span class="invalid-feedback" role="alert">
                                                                <strong>{{ $errors->first('email') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="form-group text-right">
                                                        <button class="btn btn-link p-0 text-primary" type="button" onclick="toggleEmailPhone(this)"><i>*{{ translate('Use Email Instead') }}</i></button>
                                                    </div>
                                                @else
                                                    <div class="form-group">
                                                        <label for="email" class="fs-12 fw-700 text-soft-dark">{{  translate('Email') }}</label>
                                                        <input type="email" class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email">
                                                        @if ($errors->has('email'))
                                                            <span class="invalid-feedback" role="alert">
                                                                <strong>{{ $errors->first('email') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif


                                                <div class="form-group">
                                                    <label for="phone" class="fs-12 fw-700 text-soft-dark">{{  translate('phone') }}</label>
                                                    <input type="tel" class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="{{  translate('phone') }}" name="phone" required>
                                                    @if ($errors->has('phone'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('phone') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="form-group">
                                                    <label for="country" class="fs-12 fw-700 text-soft-dark">{{  translate('Country') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('country') ? ' is-invalid' : '' }}" value="{{ old('country') }}" placeholder="{{  translate('country') }}" name="country" required>
                                                    @if ($errors->has('country'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('country') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>


                                                <!-- Tax Number -->
                                                <div class="form-group">
                                                    <label for="tax_number" class="fs-12 fw-700 text-soft-dark">{{  translate('Tax Number') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('tax_number') ? ' is-invalid' : '' }}" value="{{ old('tax_number') }}" placeholder="{{  translate('Tax Number') }}" name="tax_number" required>
                                                    @if ($errors->has('tax_number'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('tax_number') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>
                                                <!-- Company Address -->
                                                <div class="form-group">
                                                    <label for="company_address" class="fs-12 fw-700 text-soft-dark">{{  translate('Company Address') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('company_address') ? ' is-invalid' : '' }}" value="{{ old('Company Address') }}" placeholder="{{  translate('Company Address') }}" name="company_address" required>
                                                    @if ($errors->has('company_address'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('company_address') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>
                                                <!-- Company Shipping Address -->
                                                <div class="form-group">
                                                    <label for="shipping_address" class="fs-12 fw-700 text-soft-dark">{{  translate('Shipping Address') }}</label>
                                                    <input type="text" class="form-control rounded-0{{ $errors->has('shipping_address') ? ' is-invalid' : '' }}" value="{{ old('shipping_address') }}" placeholder="{{  translate('Shipping Address') }}" name="shipping_address" required>
                                                    @if ($errors->has('shipping_address'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('shipping_address') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="form-group">
                                                    <label for="trade_license" class="fs-14 fw-700 text-soft-dark">{{  translate('Trade License') }}</label>
                                                    <input type="file" class="form-control rounded-0{{ $errors->has('trade_license') ? ' is-invalid' : '' }}" value="{{ old('trade_license') }}" placeholder="{{  translate('Upload Trade License') }}" name="trade_license" required>
                                                    @if ($errors->has('trade_license'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('trade_license') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- password -->
                                                <div class="form-group">
                                                    <label for="password" class="fs-12 fw-700 text-soft-dark">{{  translate('Password') }}</label>
                                                    <input type="password" class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{  translate('Password') }}" name="password">
                                                    <div class="text-right mt-1">
                                                        <span class="fs-12 fw-400 text-gray-dark">{{ translate('Password must contain at least 6 digits') }}</span>
                                                    </div>
                                                    @if ($errors->has('password'))
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $errors->first('password') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>

                                                <!-- password Confirm -->
                                                <div class="form-group">
                                                    <label for="password_confirmation" class="fs-12 fw-700 text-soft-dark">{{  translate('Confirm Password') }}</label>
                                                    <input type="password" class="form-control rounded-0" placeholder="{{  translate('Confirm Password') }}" name="password_confirmation">
                                                </div>

                                                <!-- Recaptcha -->
                                                @if(get_setting('google_recaptcha') == 1)
                                                    <div class="form-group">
                                                        <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}"></div>
                                                    </div>
                                                @endif



                                                <!-- terms & conditions modal -->
                                                <div class="modal fade" id="terms_conditions_modal" tabindex="-1" role="dialog" aria-labelledby="account_delete_confirmModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header d-block py-4">
                                                                <div class="d-flex justify-content-center">
                                                                </div>
                                                                <h4 class="modal-title text-center fw-700" id="personalInfo_enterPassword_modalModalLabel" style="color: #ff0000;">{{ translate('Terms & Conditions')}}</h4>
                                                                <p class="fs-16 fw-600 text-center" style="color: #8d8d8d;">{{ translate('Please Read and Agree to Terms and Conditions to create an account')}}</p>
                                                            </div>

                                                            <div class="modal-body">
                                                                <textarea class="form-control" rows="20" disabled>
                                                                    {{ get_setting('terms') }}
                                                                </textarea>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="submit" id="submitButton" class="btn btn-primary btn-block fw-600 rounded-4">{{ translate('Agree') }}</button>
                                                                <button type="button" class="btn btn-secondary btn-block fw-600 rounded-4" data-dismiss="modal">{{ translate('Cancel')}}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

<!-- end modal -->

                                                <div class="mb-4 mt-4">
                                                    <a href="javascript:void(0)" onclick="terms_conditions_modal()" class="btn btn-primary btn-block fw-600 rounded-4">{{ translate('Create An Account') }}</a>

                                                </div>
                                            </form>

                                            <!-- Social Login -->
                                            @if(get_setting('google_login') == 1 || get_setting('facebook_login') == 1 || get_setting('twitter_login') == 1 || get_setting('apple_login') == 1)
                                                <div class="text-center mb-3">
                                                    <span class="bg-white fs-12 text-gray">{{ translate('Or Join With')}}</span>
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
                                            <p class="fs-12 text-gray mb-0">{{ translate('Already have an account?')}}</p>
                                            <a href="{{ route('user.login') }}" class="fs-14 fw-700 animate-underline-primary">{{ translate('Log In')}}</a>
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
                    alert("please verify you are humann!");
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

        var iti = intlTelInput(input, {
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

        var country = iti.getSelectedCountryData();
        $('input[name=country_code]').val(country.dialCode);

        input.addEventListener("countrychange", function(e) {
            // var currentMask = e.currentTarget.placeholder;

            var country = iti.getSelectedCountryData();
            $('input[name=country_code]').val(country.dialCode);

        });

        function toggleEmailPhone(el){
            if(isPhoneShown){
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                isPhoneShown = false;
                $(el).html('<i>*{{ translate('Use Phone Number Instead') }}</i>');
            }
            else{
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                isPhoneShown = true;
                $(el).html('*{{ translate('Use Email Instead') }}');
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

@endsection
