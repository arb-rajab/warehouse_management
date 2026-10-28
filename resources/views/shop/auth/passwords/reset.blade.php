@extends('shop.frontend.layouts.app')

@section('content')
<div class="py-6">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <div class="bg-white rounded shadow-sm p-4 text-left">
                    <h1 class="h3 fw-600">{{ __('storefront.auth.reset_password') }}</h1>
                    <p class="mb-4 opacity-60">{{__('storefront.auth.enter_your_email_address_and_new_password_and_confirm_password')}} </p>
                    <form method="POST" action="{{ route('shop.password.update') }}">
                        @csrf
                        <x-required-legend />

                        <div class="form-group">
                            <label for="email">{{ __('storefront.auth.email') }}<x-required-mark /></label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" placeholder="{{ __('storefront.auth.email') }}" maxlength="191" autocomplete="email" required autofocus>
                            @error('email')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="code">{{ __('storefront.common.code') }}<x-required-mark /></label>
                            <input id="code" type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ $code ?? old('code') }}" placeholder="{{ __('storefront.common.code') }}" minlength="6" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required>
                            @error('code')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">{{ __('storefront.auth.new_password') }}<x-required-mark /></label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{ __('storefront.auth.new_password') }}" minlength="6" autocomplete="new-password" required>
                            @error('password')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password-confirm">{{ __('storefront.auth.confirm_password') }}<x-required-mark /></label>
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{ __('storefront.auth.confirm_password') }}" minlength="6" autocomplete="new-password" required>
                        </div>

                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ __('storefront.auth.reset_password') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
