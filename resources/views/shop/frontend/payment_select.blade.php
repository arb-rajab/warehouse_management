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
                        <div class="col done">
                            <div class="text-center border border-bottom-6px p-2 text-success">
                                <i class="la-3x mb-2 las la-map"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ __('storefront.checkout.2_shipping_info') }}</h3>
                            </div>
                        </div>
                        <div class="col done">
                            <div class="text-center border border-bottom-6px p-2 text-success">
                                <i class="la-3x mb-2 las la-truck"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ __('storefront.checkout.3_delivery_info') }}
                                </h3>
                            </div>
                        </div>
                        <div class="col active">
                            <div class="text-center border border-bottom-6px p-2 text-primary">
                                <i class="la-3x mb-2 las la-credit-card cart-animate" style="margin-right: -100px; transition: 2s;"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ __('storefront.checkout.4_payment') }}</h3>
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

    <!-- Payment Info -->
    <section class="mb-4">
        <div class="container text-left">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <form action="{{ route('payment.checkout') }}" class="form-default" role="form" method="POST"
                        id="checkout-form">
                        @csrf
                        <input type="hidden" name="owner_id" value="{{ $carts[0]['owner_id'] }}">

                        <div class="card rounded-0 border shadow-none">
                            <div class="col-md-6 mt-3">
                                <h6 class="fs-14 fw-700 mt-3">{{ __('storefront.checkout.choose_delivery_date') }}</h6>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="row gutters-5">
                                    <div class="col-6">
                                        <label class="d-block bg-white mb-0">
                                            <input
                                                class="form-control"
                                                type="date"
                                                name="delivery_date"
                                                id="deliveryDate"
                                                required
                                            >
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <!-- Additional Info -->
                            <div class="card-header p-4 border-bottom-0">
                                <h3 class="fs-16 fw-700 text-dark mb-0">
                                    {{ __('storefront.common.any_additional_info') }}
                                </h3>
                            </div>
                            <div class="form-group px-4">
                                <textarea name="additional_info" rows="5" class="form-control rounded-0" placeholder="{{ __('storefront.common.type_your_text') }}"></textarea>
                            </div>

                            <div class="card-header p-4 border-bottom-0 d-none">
                                <h3 class="fs-16 fw-700 text-dark mb-0">
                                    {{ __('storefront.checkout.select_a_payment_option') }}
                                </h3>
                            </div>
                            <!-- Payment Options -->
                            <div class="card-body text-center px-4 pt-0 d-none">
                                <div class="row gutters-10">
                                    {{--
                                    <!-- sslcommerz -->
                                    @if (get_setting('sslcommerz_payment') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="sslcommerz" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/sslcommerz.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.sslcommerz') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- voguepay -->
                                    @if (get_setting('voguepay') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="voguepay" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/vogue.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.voguepay') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- payhere -->
                                    @if (get_setting('payhere') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="payhere" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/payhere.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.payhere') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- ngenius -->
                                    @if (get_setting('ngenius') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="ngenius" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/ngenius.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.ngenius') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- iyzico -->
                                    @if (get_setting('iyzico') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="iyzico" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/iyzico.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.iyzico') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- nagad -->
                                    @if (get_setting('nagad') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="nagad" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/nagad.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.nagad') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- bkash -->
                                    @if (get_setting('bkash') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="bkash" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/bkash.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.bkash') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    <!-- aamarpay -->
                                    @if (get_setting('aamarpay') == 1)
                                        <div class="col-6 col-xl-3 col-md-4">
                                            <label class="aiz-megabox d-block mb-3">
                                                <input value="aamarpay" class="online_payment" type="radio"
                                                    name="payment_option" checked>
                                                <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                    <img src="{{ static_asset('assets/img/cards/aamarpay.png') }}"
                                                        class="img-fit mb-2">
                                                    <span class="d-block text-center">
                                                        <span
                                                            class="d-block fw-600 fs-15">{{ __('storefront.common.aamarpay') }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                    --}}
                                    <!-- Cash Payment -->
                                    @if (get_setting('cash_payment') == 1)
                                        @php
                                            $digital = 0;
                                            $cod_on = 1;
                                            // foreach ($carts as $cartItem) {
                                            //     $product = \App\Models\Product::find($cartItem['product_id']);
                                            //     if ($product['digital'] == 1) {
                                            //         $digital = 1;
                                            //     }
                                            //     if ($product['cash_on_delivery'] == 0) {
                                            //         $cod_on = 0;
                                            //     }
                                            // }
                                        @endphp
                                        @if ($digital != 1 && $cod_on == 1)
                                            <div class="col-6 col-xl-3 col-md-4">
                                                <label class="aiz-megabox d-block mb-3">
                                                    <input value="cash_on_delivery" class="online_payment"
                                                        type="radio" name="payment_option" checked>
                                                    <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                        <img src="{{ static_asset('assets/img/cards/cod.png') }}"
                                                            class="img-fit mb-2">
                                                        <span class="d-block text-center">
                                                            <span
                                                                class="d-block fw-600 fs-15">{{ __('storefront.checkout.cash_on_delivery') }}</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endif
                                    @endif
                                    {{-- @if (Auth::check())
                                        <!-- Offline Payment -->
                                        @if (addon_is_activated('offline_payment'))
                                            @foreach (\App\Models\ManualPaymentMethod::all() as $method)
                                                <div class="col-6 col-xl-3 col-md-4">
                                                    <label class="aiz-megabox d-block mb-3">
                                                        <input value="{{ $method->heading }}" type="radio"
                                                            name="payment_option" class="offline_payment_option"
                                                            onchange="toggleManualPaymentData({{ $method->id }})"
                                                            data-id="{{ $method->id }}" checked>
                                                        <span class="d-block aiz-megabox-elem rounded-0 p-3">
                                                            <img src="{{ uploaded_asset($method->photo) }}"
                                                                class="img-fit mb-2">
                                                            <span class="d-block text-center">
                                                                <span
                                                                    class="d-block fw-600 fs-15">{{ $method->heading }}</span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach

                                            @foreach (\App\Models\ManualPaymentMethod::all() as $method)
                                                <div id="manual_payment_info_{{ $method->id }}" class="d-none">
                                                    @php echo $method->description @endphp
                                                    @if ($method->bank_info != null)
                                                        <ul>
                                                            @foreach (json_decode($method->bank_info) as $key => $info)
                                                                <li>{{ __('storefront.common.bank_name') }} -
                                                                    {{ $info->bank_name }},
                                                                    {{ __('storefront.auth.account_name') }} -
                                                                    {{ $info->account_name }},
                                                                    {{ __('storefront.auth.account_number') }} -
                                                                    {{ $info->account_number }},
                                                                    {{ __('storefront.common.routing_number') }} -
                                                                    {{ $info->routing_number }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    @endif --}}
                                </div>

                                <!-- Offline Payment Fields -->
                                {{-- @if (addon_is_activated('offline_payment'))
                                    <div class="d-none mb-3 rounded border bg-white p-3 text-left">
                                        <div id="manual_payment_description">

                                        </div>
                                        <br>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label>{{ __('storefront.checkout.transaction_id') }} <span
                                                        class="text-danger">*</span></label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control mb-3" name="trx_id"
                                                    id="trx_id" placeholder="{{ __('storefront.checkout.transaction_id') }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-md-3 col-form-label">{{ __('storefront.common.photo') }}</label>
                                            <div class="col-md-9">
                                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                            {{ __('storefront.upload.browse') }}</div>
                                                    </div>
                                                    <div class="form-control file-amount">{{ __('storefront.common.choose_image') }}
                                                    </div>
                                                    <input type="hidden" name="photo" class="selected-files">
                                                </div>
                                                <div class="file-preview box sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif --}}

                                <!-- Wallet Payment -->
                                {{-- @if (Auth::check() && get_setting('wallet_system') == 1)
                                    <div class="py-4 px-4 text-center bg-soft-warning mt-4">
                                        <div class="fs-14 mb-3">
                                            <span class="opacity-80">{{ __('storefront.checkout.or_your_wallet_balance') }}</span>
                                            <span class="fw-700">{{ single_price(Auth::user()->balance) }}</span>
                                        </div>
                                        @if (Auth::user()->balance < $total)
                                            <button type="button" class="btn btn-secondary" disabled>
                                                {{ __('storefront.common.insufficient_balance') }}
                                            </button>
                                        @else
                                            <button type="button" onclick="use_wallet()" class="btn btn-primary fs-14 fw-700 px-5 rounded-0">
                                                {{ __('storefront.checkout.pay_with_wallet') }}
                                            </button>
                                        @endif
                                    </div>
                                @endif --}}
                            </div>

                            <!-- Agree Box -->
                            <div class="pt-3 px-4 fs-14">
                                <label class="aiz-checkbox">
                                    <input type="checkbox" required id="agree_checkbox">
                                    <span class="aiz-square-check"></span>
                                    <span>{{ __('storefront.common.i_agree_to_the') }}</span>
                                </label>
                                <a href="{{ route('terms') }}" class="fw-700">{{ __('storefront.common.terms_and_conditions') }}</a>,
                                <a href="{{ route('returnpolicy') }}" class="fw-700">{{ __('storefront.common.return_policy') }}</a> &
                                <a href="{{ route('privacypolicy') }}" class="fw-700">{{ __('storefront.common.privacy_policy') }}</a>
                            </div>

                            @if(get_setting('minimum_order_amount_check') == 1)
                            <div class="pt-3 px-4 fs-14">
                                <label class="">
                                    @php
                                        $default_delivery_fees = \App\Models\Product::where('mat_id', get_setting('default_specific_product'))->first();
                                        if($default_delivery_fees){
                                            $default_delivery_fees =  $default_delivery_fees->unit_price;
                                        }
                                        else{
                                            $default_delivery_fees = 0;
                                        }
                                    @endphp
                                    <span>* {{ __('storefront.checkout.if_the_order_s_total_is_less_than') }}: €{{ get_setting('minimum_order_amount') }},</span>
                                    <p>{!! str_replace(':amount', '<span class="text-danger">€'.e($default_delivery_fees).'</span>', e(__('storefront.checkout.shipping_fee'))) !!}</p>
                                </label>
                            </div>
                            @endif
                            <div class="row align-items-center pt-3 px-4 mb-4">
                                <!-- Return to shop -->
                                <div class="col-6">
                                    <a href="{{ route('home') }}" class="btn btn-link fs-14 fw-700 px-0">
                                        <i class="las la-arrow-left fs-16"></i>
                                        {{ __('storefront.common.return_to_shop') }}
                                    </a>
                                </div>
                                <!-- Complete Ordert -->
                                <div class="col-6 text-right">
                                    <button type="button" onclick="submitOrder(this)"
                                        class="btn btn-primary fs-14 fw-700 rounded-0 px-4">{{ __('storefront.checkout.complete_order') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Cart Summary -->
                @if(Auth::check() && Auth::user()->admin_verified)
                <div class="col-lg-4 mt-lg-0 mt-4" id="cart_summary">
                    {{-- @include('shop.frontend.partials.cart_summary') --}}
                </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            $(".online_payment").click(function() {
                $('#manual_payment_description').parent().addClass('d-none');
            });
            toggleManualPaymentData($('input[name=payment_option]:checked').data('id'));
        });

        var minimum_order_amount_check = {{ get_setting('minimum_order_amount_check') == 1 ? 1 : 0 }};
        var minimum_order_amount =
            {{ get_setting('minimum_order_amount_check') == 1 ? get_setting('minimum_order_amount') : 0 }};

        function use_wallet() {
            $('input[name=payment_option]').val('wallet');
            if ($('#agree_checkbox').is(":checked")) {
                ;
                if (minimum_order_amount_check && $('#sub_total').val() < minimum_order_amount) {
                    AIZ.plugins.notify('danger',
                        {{ \Illuminate\Support\Js::from(__('storefront.checkout.you_order_amount_is_less_then_the_minimum_order_amount')) }});
                } else {
                    $('#checkout-form').submit();
                }
            } else {
                AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.you_need_to_agree_with_our_policies')) }});
            }
        }

        function submitOrder(el) {
            $(el).prop('disabled', true);
            var deliveryDateValue = $('#deliveryDate').val();
            if (deliveryDateValue.trim() === '') {
                AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.checkout.delivery_date_is_required')) }});
            }
            if ($('#agree_checkbox').is(":checked")) {
                if (minimum_order_amount_check && $('#sub_total').val() < minimum_order_amount) {
                    AIZ.plugins.notify('danger',
                        {{ \Illuminate\Support\Js::from(__('storefront.checkout.you_order_amount_is_less_then_the_minimum_order_amount')) }});
                } else {
                    var offline_payment_active = '{{ addon_is_activated('offline_payment') }}';
                    if (offline_payment_active == 'true' && $('.offline_payment_option').is(":checked") && $('#trx_id')
                        .val() == '') {
                        AIZ.plugins.notify('danger',
                            {{ \Illuminate\Support\Js::from(__('storefront.checkout.you_need_to_put_transaction_id')) }});
                        $(el).prop('disabled', false);
                    } else {
                        $('#checkout-form').submit();
                    }
                }
            } else {
                AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.you_need_to_agree_with_our_policies')) }});
                $(el).prop('disabled', false);
            }
        }

        function toggleManualPaymentData(id) {
            if (typeof id != 'undefined') {
                $('#manual_payment_description').parent().removeClass('d-none');
                $('#manual_payment_description').html($('#manual_payment_info_' + id).html());
            }
        }

        $(document).on("click", "#coupon-apply", function() {
            var data = new FormData($('#apply-coupon-form')[0]);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: "POST",
                url: "{{ route('checkout.apply_coupon_code') }}",
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data, textStatus, jqXHR) {
                    AIZ.plugins.notify(data.response_message.response, data.response_message.message);
                    $("#cart_summary").html(data.html);
                }
            })
        });

        $(document).on("click", "#coupon-remove", function() {
            var data = new FormData($('#remove-coupon-form')[0]);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: "POST",
                url: "{{ route('checkout.remove_coupon_code') }}",
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data, textStatus, jqXHR) {
                    $("#cart_summary").html(data);
                }
            })
        })
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var deliveryDateInput = document.getElementById('deliveryDate');

            var today = new Date().toISOString().split('T')[0];
            deliveryDateInput.setAttribute('min', today);

            var twoDaysFromNow = new Date();
            twoDaysFromNow.setDate(twoDaysFromNow.getDate() + 2);
            var minDate = twoDaysFromNow.toISOString().split('T')[0];
            deliveryDateInput.setAttribute('min', minDate);

            deliveryDateInput.addEventListener('input', function () {
                var selectedDate = new Date(this.value);
                var dayOfWeek = selectedDate.getDay();

                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    this.value = '';
                    alert({{ \Illuminate\Support\Js::from(__('storefront.checkout.weekday_required')) }});
                }
            });
        });
    </script>
@endsection
