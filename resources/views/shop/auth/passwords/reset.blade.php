@extends('shop.frontend.layouts.app')

@section('content')
<div class="py-6">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <div class="bg-white rounded shadow-sm p-4 text-left">
                    <h1 class="h3 fw-600">{{ translate('Reset Password') }}</h1>
                    <p class="mb-4 opacity-60">{{translate('Enter your email address and new password and confirm password.')}} </p>
                    <form method="POST" action="{{ route('shop.password.update') }}">
                        @csrf
                        <x-required-legend />

                        <div class="form-group">
                            <label for="email">{{ translate('Email') }}<x-required-mark /></label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" placeholder="{{ translate('Email') }}" maxlength="191" autocomplete="email" required autofocus>
                            @error('email')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="code">{{ translate('Code') }}<x-required-mark /></label>
                            <input id="code" type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ $code ?? old('code') }}" placeholder="{{ translate('Code') }}" minlength="6" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required>
                            @error('code')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">{{ translate('New Password') }}<x-required-mark /></label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{ translate('New Password') }}" minlength="6" autocomplete="new-password" required>
                            @error('password')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password-confirm">{{ translate('Confirm Password') }}<x-required-mark /></label>
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{ translate('Confirm Password') }}" minlength="6" autocomplete="new-password" required>
                        </div>

                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ translate('Reset Password') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
