@extends('shop.frontend.layouts.app')

@section('content')
<section class="pt-4 mb-4">
    <div class="container text-center">
        <div class="row">
            <div class="col-lg-6 text-center text-lg-left">
                {{-- <h1 class="fw-700 fs-24 text-dark">{{ translate('Register your shop')}}</h1> --}}
            </div>
            <div class="col-lg-6">
                <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
                    <li class="breadcrumb-item opacity-50">
                        <a class="text-reset" href="{{ route('home') }}">{{ translate('Home')}}</a>
                    </li>
                    <li class="text-dark fw-600 breadcrumb-item">
                        <a class="text-dark" href="{{ route('shops.create') }}">"{{ translate('Register your shop')}}"</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<section class="pt-4 mb-4">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <h1 class="fw-700 fs-20 fs-md-24 text-dark text-center mb-3">{{ translate('Register Your Shop')}}</h1>
                <form id="shop" class="" action="{{ route('shops.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <x-required-legend />
                    @if (!Auth::check())
                        <div class="bg-white border mb-4">
                            <div class="fs-15 fw-600 p-3">
                                {{ translate('Personal Info')}}
                            </div>
                            <div class="p-3">
                                <div class="form-group">
                                    <label for="seller_name">{{ translate('Your Name')}}<x-required-mark /></label>
                                    <input type="text" id="seller_name" class="form-control rounded-0 @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="{{  translate('Name') }}" name="name" maxlength="191" required>
                                    @error('name')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="seller_email">{{ translate('Your Email')}}<x-required-mark /></label>
                                    <input type="email" id="seller_email" class="form-control rounded-0 @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email" maxlength="191" required>
                                    @error('email')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="seller_password">{{ translate('Your Password')}}<x-required-mark /></label>
                                    <input type="password" id="seller_password" class="form-control rounded-0 @error('password') is-invalid @enderror" placeholder="{{  translate('Password') }}" name="password" minlength="6" autocomplete="new-password" required>
                                    @error('password')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="seller_password_confirmation">{{ translate('Repeat Password')}}<x-required-mark /></label>
                                    <input type="password" id="seller_password_confirmation" class="form-control rounded-0" placeholder="{{  translate('Confirm Password') }}" name="password_confirmation" minlength="6" autocomplete="new-password" required>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="bg-white border mb-4">
                        <div class="fs-15 fw-600 p-3">
                            {{ translate('Basic Info')}}
                        </div>
                        <div class="p-3">
                            <div class="form-group">
                                <label for="seller_shop_name">{{ translate('Shop Name')}}<x-required-mark /></label>
                                <input type="text" id="seller_shop_name" class="form-control rounded-0 @error('shop_name') is-invalid @enderror" value="{{ old('shop_name') }}" placeholder="{{ translate('Shop Name')}}" name="shop_name" maxlength="200" required>
                                @error('shop_name')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="seller_shop_address">{{ translate('Address')}}<x-required-mark /></label>
                                <input type="text" id="seller_shop_address" class="form-control mb-3 rounded-0 @error('address') is-invalid @enderror" value="{{ old('address') }}" placeholder="{{ translate('Address')}}" name="address" maxlength="500" required>
                                @error('address')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    @if(get_setting('google_recaptcha') == 1)
                        <div class="form-group mt-2 mx-auto row">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            @error('g-recaptcha-response')
                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    @endif

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary fw-600 rounded-0">{{ translate('Register Your Shop')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection

@section('script')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script type="text/javascript">
    // making the CAPTCHA  a required field for form submission
    $(document).ready(function(){
        $("#shop").on("submit", function(evt)
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
</script>
@endsection
