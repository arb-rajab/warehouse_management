@extends('shop.frontend.layouts.app')

@section('content')
    <section class="pt-4 mb-4">
        <div class="container text-center">
            <div class="row">
                <div class="col-lg-6 text-center text-lg-left">
                    <h1 class="fw-700 fs-20 fs-md-24 text-dark">{{ __('storefront.checkout.track_order') }}</h1>
                </div>
                <div class="col-lg-6">
                    <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
                        <li class="breadcrumb-item opacity-50">
                            <a class="text-reset" href="{{ route('home') }}">{{ __('storefront.navigation.home') }}</a>
                        </li>
                        <li class="text-dark fw-600 breadcrumb-item">
                            <a class="text-reset" href="{{ route('orders.track') }}">"{{ __('storefront.checkout.track_order') }}"</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <section class="mb-5">
        <div class="container text-left">
            <div class="row">
                <div class="col-xxl-5 col-xl-6 col-lg-8 mx-auto">
                    <form class="" action="{{ route('orders.track') }}" method="GET" enctype="multipart/form-data">
                        <div class="bg-white border rounded-0">
                            <div class="fs-15 fw-600 p-3 border-bottom text-center">
                                {{ __('storefront.checkout.check_your_order_status')}}
                            </div>
                            <div class="form-box-content p-3">
                                <div class="form-group">
                                    <input type="text" class="form-control rounded-0 mb-3" placeholder="{{ __('storefront.checkout.order_code_label')}}" name="order_code" required>
                                </div>
                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary rounded-0 w-150px">{{ __('storefront.checkout.track_order')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @isset($order)
                <div class="bg-white border rounded-0 mt-5">
                    <div class="fs-15 fw-600 p-3">
                        {{ __('storefront.checkout.order_summary')}}
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-lg-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.order_code_label')}}:</td>
                                        <td>{{ $order->code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.common.customer')}}:</td>
                                        <td>{{ json_decode($order->shipping_address)->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.auth.email')}}:</td>
                                        @if ($order->user_id != null)
                                            <td>{{ $order->user->email }}</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.shipping_address')}}:</td>
                                        <td>{{ json_decode($order->shipping_address)->address }}, {{ json_decode($order->shipping_address)->city }}, {{ json_decode($order->shipping_address)->country }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-lg-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.order_date')}}:</td>
                                        <td>{{ \App\Support\StoreLocale::date($order->date, true) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.total_order_amount')}}:</td>
                                        <td>{{ single_price($order->orderDetails->sum('price') + $order->orderDetails->sum('tax')) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.shipping_method')}}:</td>
                                        <td>{{ __('storefront.checkout.flat_shipping_rate')}}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.payment_method')}}:</td>
                                        <td>{{ \App\Support\StoreLocale::status($order->payment_type) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="w-50 fw-600">{{ __('storefront.checkout.delivery_status')}}:</td>
                                        <td>{{ \App\Support\StoreLocale::status($order->delivery_status) }}</td>
                                    </tr>
                                    @if ($order->tracking_code)
                                        <tr>
                                            <td class="w-50 fw-600">{{ __('storefront.common.tracking_code')}}:</td>
                                            <td>{{ $order->tracking_code }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>


                @foreach ($order->orderDetails as $key => $orderDetail)
                    @php
                        $status = $order->delivery_status;
                    @endphp
                    <div class="bg-white border rounded-0 mt-4">

                        @if($orderDetail->product != null)
                        <div class="p-3">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="border-0">{{ __('storefront.product.product_name')}}</th>
                                        <th class="border-0">{{ __('storefront.product.quantity')}}</th>
                                        <th class="border-0">{{ __('storefront.common.shipped_by')}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                    <td>{{ $orderDetail->product->getTranslation('name') }} ({{ $orderDetail->entry_unit ?? $orderDetail->variation }})</td>
                                        <td>{{ $orderDetail->quantity }}</td>
                                        <td>{{ $orderDetail->product->user->name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                @endforeach

            @endisset
        </div>
    </section>
@endsection
