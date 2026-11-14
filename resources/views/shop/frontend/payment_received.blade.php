@extends('shop.frontend.layouts.app')

{{-- After a gateway return, for a browser that is not signed in as the payer
     (App\Http\Controllers\GatewayPaymentLandingController). It names nothing
     about the payment: no amount, order or account. --}}
@section('content')
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-xl-6 col-lg-8 mx-auto text-center">
                    <i class="las la-check-circle la-4x text-success mb-3" aria-hidden="true"></i>
                    <h1 class="fs-24 fw-700 mb-3">{{ __('storefront.checkout.payment_received') }}</h1>
                    <p class="fs-16 text-secondary mb-4">{{ __('storefront.checkout.payment_received_sign_in') }}</p>
                    <a href="{{ route('shop.login') }}" class="btn btn-primary fw-600 mr-2">{{ __('storefront.auth.login') }}</a>
                    <a href="{{ route('home') }}" class="btn btn-soft-primary fw-600">{{ __('storefront.checkout.payment_received_back_to_shop') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
