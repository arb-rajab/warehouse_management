@extends('shop.frontend.layouts.app')

@section('content')
<div class="py-6">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <div class="bg-white rounded shadow-sm p-4 text-left">
                    <h1 class="h3 fw-600 mb-3">{{ __('storefront.auth.verify_your_email_address') }}</h1>
                    <p class="opacity-60">
                        {{ __('storefront.auth.before_proceeding_please_check_your_email_for_a_verification_link') }}
                        {{ __('storefront.auth.if_you_did_not_receive_the_email') }}
                    </p>
                    <a href="{{ route('verification.resend') }}" class="btn btn-primary btn-block">{{ __('storefront.auth.click_here_to_request_another') }}</a>
                    @if (session('resent'))
                        <div class="alert alert-success mt-2 mb-0" role="alert">
                            {{ __('storefront.auth.a_fresh_verification_link_has_been_sent_to_your_email_address') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
