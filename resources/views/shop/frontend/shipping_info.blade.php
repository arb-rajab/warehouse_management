@extends('shop.frontend.layouts.app')

@section('content')

    <!-- Steps -->
    <section class="pt-5 mb-4">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 mx-auto">
                    <div class="row gutters-5 sm-gutters-10">
                        <div class="col done">
                            <div class="text-center border border-bottom-6px p-2 text-success">
                                <i class="la-3x mb-2 las la-shopping-cart"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ __('storefront.cart.1_my_cart') }}</h3>
                            </div>
                        </div>
                        <div class="col active">
                            <div class="text-center border border-bottom-6px p-2 text-primary">
                                <i class="la-3x mb-2 las la-map cart-animate" style="margin-right: -100px; transition: 2s;"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ __('storefront.checkout.2_shipping_info') }}
                                </h3>
                            </div>
                        </div>
                        <div class="col">
                            <div class="text-center border border-bottom-6px p-2">
                                <i class="la-3x mb-2 opacity-50 las la-truck"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block opacity-50">{{ __('storefront.checkout.3_delivery_info') }}
                                </h3>
                            </div>
                        </div>
                        <div class="col">
                            <div class="text-center border border-bottom-6px p-2">
                                <i class="la-3x mb-2 opacity-50 las la-credit-card"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block opacity-50">{{ __('storefront.checkout.4_payment') }}</h3>
                            </div>
                        </div>
                        <div class="col">
                            <div class="text-center border border-bottom-6px p-2">
                                <i class="la-3x mb-2 opacity-50 las la-check-circle"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block opacity-50">{{ __('storefront.common.5_confirmation') }}
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Shipping Info -->
    <section class="mb-4 gry-bg">
        <div class="container">
            <div class="row cols-xs-space cols-sm-space cols-md-space">
                <div class="col-xxl-8 col-xl-10 mx-auto">
                    <form class="form-default" data-toggle="validator" action="{{ route('checkout.store_shipping_infostore') }}" role="form" method="POST">
                        @csrf
                        @if(Auth::check())
                            <div class="border bg-white p-4 mb-4">
                                <x-required-legend />
                                <fieldset>
                                <legend class="fs-16 fw-700 mb-3">{{ __('storefront.checkout.shipping_address') }}<x-required-mark /></legend>
                                @error('address_id')
                                    <div class="invalid-feedback d-block mb-3" role="alert"><strong>{{ $message }}</strong></div>
                                @enderror
                                @foreach ($customer_addresses as $key => $address)
                                <div class="border mb-4">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="aiz-megabox d-block bg-white mb-0">
                                                <input type="radio" name="address_id" value="{{ $address->id }}" @if ($address->set_default)
                                                    checked
                                                @endif required>
                                                <span class="d-flex p-3 aiz-megabox-elem border-0">
                                                    <!-- Checkbox -->
                                                    <span class="aiz-rounded-check flex-shrink-0 mt-1"></span>
                                                    <!-- Address -->
                                                    <span class="flex-grow-1 pl-3 text-left">
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.address') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ $address->address }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.postal_code') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ $address->postal_code }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.city') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ optional($address->city)->name }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.state') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ optional($address->state)->name }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.country') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ optional($address->country)->name }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.auth.phone') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ $address->phone }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <span class="fs-14 text-secondary col-3">{{ __('storefront.common.postal_code') }}</span>
                                                            <span class="fs-14 text-dark fw-500 ml-2 col">{{ $address->postal_code }}</span>
                                                        </div>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                        <!-- Edit Address Button -->
                                        <div class="col-md-4 p-3 text-right">
                                            <a class="btn btn-sm btn-warning text-white mr-4 rounded-0 px-4" onclick="edit_address('{{$address->id}}')">{{ __('storefront.common.edit') }}</a>
                                            <a class="btn btn-sm btn-warning text-white mr-4 rounded-0 px-4 confirm-delete" href="#" data-method="post" data-href="{{ route('addresses.destroy', $address->id) }}">{{ __('storefront.common.delete') }}</a>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                                </fieldset>

                                <!-- Add New Address -->
                                <div class="mb-5" >
                                    <div class="border p-3 c-pointer text-center bg-light has-transition hov-bg-soft-light h-100 d-flex flex-column justify-content-center" onclick="add_new_address()">
                                        <i class="las la-plus la-2x mb-3"></i>
                                        <div class="alpha-7 fw-700">{{ __('storefront.common.add_new_address') }}</div>
                                    </div>
                                </div>
                                <div class="row align-items-center">
                                    <!-- Return to shop -->
                                    <div class="col-md-6 text-center text-md-left order-1 order-md-0">
                                        <a href="{{ route('home') }}" class="btn btn-link fs-14 fw-700 px-0">
                                            <i class="las la-arrow-left fs-16"></i>
                                            {{ __('storefront.common.return_to_shop')}}
                                        </a>
                                    </div>
                                    <!-- Continue to Delivery Info -->
                                    <div class="col-md-6 text-center text-md-right">
                                        <button type="submit" class="btn btn-primary fs-14 fw-700 rounded-0 px-4">{{ __('storefront.checkout.continue_to_delivery_info')}}</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('modal')
    <!-- Address Modal -->
    @include('shop.frontend.partials.address_modal')

    <!-- Delete Modal -->
    @include('shop.modals.delete_modal')
@endsection
