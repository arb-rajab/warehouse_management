@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <!-- Order id -->
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.checkout.order_id') }}: {{ $order->code }}</h1>
            </div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="card rounded-0 shadow-none border mb-4">
        <div class="card-header border-bottom-0">
            <h5 class="fs-16 fw-700 text-dark mb-0">{{ __('storefront.checkout.order_summary') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">

                <div class="col-lg-6">
                    <table class="table-borderless table">
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.order_code_label') }}:</td>
                            <td>{{ $order->code }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-800">{{ __('storefront.checkout.order_by') }}:</td>
                            <td class="fw-800">{{ json_decode($order->shipping_address)->name }}</td>
                        </tr>

                        @if($order->user_id != null && ! $order->by_rep)
                        <tr>
                            <td class="w-50 fw-800">{{ __('storefront.common.customer_otajer_id') }}:</td>
                            <td class="fw-800"> {{ $order->user->AccSysID }}</td>
                        </tr>
                        @endif

                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.auth.email') }}:</td>
                            @if ($order->user_id != null)
                                <td>{{ $order->user->email }}</td>
                            @endif
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.shipping_address') }}:</td>
                            <td>{{ json_decode($order->shipping_address)->address }},
                                {{ json_decode($order->shipping_address)->city }},
                                @if(isset(json_decode($order->shipping_address)->state)) {{ json_decode($order->shipping_address)->state }} - @endif
                                {{ json_decode($order->shipping_address)->postal_code }},
                                {{ json_decode($order->shipping_address)->country }}
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-lg-6">
                    <table class="table-borderless table">
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.order_date') }}:</td>
                            <td>{{ \App\Support\StoreLocale::date($order->date, true) }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.order_status') }}:</td>
                            <td>{{ \App\Support\StoreLocale::status($order->delivery_status) }}</td>
                        </tr>
                        @if(Auth::check() && Auth::user()->admin_verified)
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.total_order_amount') }}:</td>
                            <td>{{ single_price($order->orderDetails->sum('price')) }}
                            </td>
                        </tr>
                        @endif
                        {{--
                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.shipping_method') }}:</td>
                            <td>{{ __('storefront.checkout.flat_shipping_rate') }}</td>
                        </tr>

                        <tr>
                            <td class="w-50 fw-600">{{ __('storefront.checkout.payment_method') }}:</td>
                            <td>{{ \App\Support\StoreLocale::status($order->payment_type) }}</td>
                        </tr>
                         --}}
                        <tr>
                            <td class="text-main w-50 fw-800">{{ __('storefront.checkout.order_notes') }}</td>
                            <td class="fw-800">{{ $order->additional_info }}</td>
                        </tr>
                        @if ($order->by_rep)
                            <tr>
                                <td class="text-main fw-800">{{ __('storefront.common.by_representative') }}</td>
                                <td class="fw-800">{{ $order->user->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-main fw-800">{{ __('storefront.common.for_customer') }}</td>
                                <td class="fw-800">{{ $order->rep->name }}</td>
                            </tr>

                            <tr>
                                <td class="text-main fw-800">{{ __('storefront.common.customer_otajer_id_label') }}</td>
                                <td class="fw-800">{{ $order->rep->AccSysID }}</td>
                            </tr>
                        @endif
                        @if ($order->tracking_code)
                            <tr>
                                <td class="w-50 fw-600">{{ __('storefront.common.tracking_code') }}:</td>
                                <td>{{ $order->tracking_code }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details -->
    <div class="row gutters-16">
        <div class="col-md-9">
            <div class="card rounded-0 shadow-none border mt-2 mb-4">
                <div class="card-header border-bottom-0">
                    <h5 class="fs-16 fw-700 text-dark mb-0">{{ __('storefront.checkout.order_details') }}</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="aiz-table table">
                        <thead class="text-gray fs-12">
                            <tr>
                                <th class="pl-0">#</th>
                                <th width="30%">{{ __('storefront.product.product') }}</th>
                                <th data-breakpoints="md">{{ __('storefront.common.unit') }}</th>
                                <th data-breakpoints="md">{{ __('storefront.common.variation') }}</th>

                                {{-- <th data-breakpoints="md">{{ __('storefront.checkout.delivery_type') }}</th> --}}
                                <th>{{ __('storefront.common.item_notes') }}</th>
                                <th>{{ __('storefront.product.quantity') }}</th>
                                @if(Auth::check() && Auth::user()->admin_verified)
                                <th>{{ __('storefront.product.price') }}</th>
                                @endif
                                @if (addon_is_activated('refund_request'))
                                    <th data-breakpoints="md">{{ __('storefront.common.refund') }}</th>
                                @endif
                                @if(get_setting("Product_Reviews_Section") == 1)
                                <th data-breakpoints="md" class="text-right pr-0">{{ __('storefront.product.review') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="fs-14">
                            @php
                            $currentPage = request()->get('page', 1);
                            $perPage = 10; // Set the number of items per page
                            $offset = ($currentPage - 1) * $perPage;
                            $paginatedOrderDetails = $order->orderDetails->slice($offset, $perPage);
                            $paginator = new \Illuminate\Pagination\LengthAwarePaginator($paginatedOrderDetails, $order->orderDetails->count(), $perPage, $currentPage);
                            $paginator->setPath(request()->url());
                        @endphp
                            @foreach ($paginator as $key => $orderDetail)
                                <tr>
                                    <td class="pl-0">{{ sprintf('%02d', $key+1) }}</td>
                                    <td>
                                        @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                            <a href="{{ \App\Support\StorefrontUrl::to($orderDetail->product) }}"
                                                target="_blank">{{ $orderDetail->product->getTranslation('name') }}</a>
                                        @elseif($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <a href="{{ \App\Support\StorefrontUrl::to($orderDetail->product) }}"
                                                target="_blank">{{ $orderDetail->product->getTranslation('name') }}</a>
                                        @else
                                            <strong>{{ __('storefront.product.product_unavailable') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $orderDetail->entry_unit ?? $orderDetail->variation }}
                                    </td>

                                    <!-- Variation Qty  -->

                                    <td>
                                        @php
                                            $variations_qty = json_decode($orderDetail->variation_qty, true);
                                        @endphp
                                            @if ($variations_qty)
                                                @foreach ($variations_qty as $key => $value)
                                                    {{ $key }}: {{ $value }}<br>
                                                @endforeach
                                            @endif

                                    </td>


                                    {{-- <td>
                                        @if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
                                            {{ __('storefront.checkout.standard_delivery') }}
                                        @elseif ($order->shipping_type == 'pickup_point')
                                            @if ($order->pickup_point != null)
                                                {{ $order->pickup_point->name }} ({{ __('storefront.common.pickip_point') }})
                                            @else
                                                {{ __('storefront.checkout.pickup_point') }}
                                            @endif
                                        @elseif($order->shipping_type == 'carrier')
                                            @if ($order->carrier != null)
                                                {{ $order->carrier->name }} ({{ __('storefront.checkout.carrier') }})
                                                <br>
                                                {{ __('storefront.common.transit_time').' - '.$order->carrier->transit_time }}
                                            @else
                                                {{ __('storefront.checkout.carrier') }}
                                            @endif
                                        @endif
                                    </td> --}}
                                    <td>
                                            {{ $orderDetail->item_notes }}
                                    </td>

                                    <td>
                                        {{ $orderDetail->quantity }}
                                    </td>


                                    @if(Auth::check() && Auth::user()->admin_verified)
                                    <td class="fw-700">{{ single_price($orderDetail->price) }}</td>
                                    @endif

                                    @if (addon_is_activated('refund_request'))
                                        @php
                                            $no_of_max_day = get_setting('refund_request_time');
                                            $last_refund_date = $orderDetail->created_at->addDays($no_of_max_day);
                                            $today_date = Carbon\Carbon::now();
                                        @endphp
                                        <td>
                                            @if ($orderDetail->product != null && $orderDetail->product->refundable != 0 && $orderDetail->refund_request == null && $today_date <= $last_refund_date && $orderDetail->payment_status == 'paid' && $orderDetail->delivery_status == 'delivered')
                                                <a href="{{ route('refund_request_send_page', $orderDetail->id) }}"
                                                    class="btn btn-primary btn-sm rounded-0">{{ __('storefront.common.send') }}</a>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 0)
                                                <b class="text-info">{{ __('storefront.common.pending') }}</b>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 2)
                                                <b class="text-success">{{ __('storefront.common.rejected') }}</b>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 1)
                                                <b class="text-success">{{ __('storefront.common.approved') }}</b>
                                            @elseif ($orderDetail->product->refundable != 0)
                                                <b>{{ __('storefront.common.n_a') }}</b>
                                            @else
                                                <b>{{ __('storefront.common.non_refundable') }}</b>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="text-xl-right pr-0">
                                        @if(get_setting("Product_Reviews_Section") == 1)
                                        @if ($orderDetail->delivery_status == 'delivered')
                                            <a href="javascript:void(0);"
                                                onclick="product_review('{{ $orderDetail->product_id }}')"
                                                class="btn btn-primary btn-sm rounded-0"> {{ __('storefront.product.review') }} </a>
                                        @else
                                            <span class="text-danger">{{ __('storefront.common.not_delivered_yet') }}</span>
                                        @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-center">
                        {{ $paginator->links() }} <!-- Display pagination links -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Ammount -->
        @if(Auth::check() && Auth::user()->admin_verified)
        <div class="col-md-3">
            <div class="card rounded-0 shadow-none border mt-2">
                <div class="card-header border-bottom-0">
                    <b class="fs-16 fw-700 text-dark">{{ __('storefront.checkout.order_ammount') }}</b>
                </div>
                <div class="card-body pb-0">
                    <table class="table-borderless table">
                        <tbody>
                            <tr>
                                <td class="w-50 fw-600">{{ __('storefront.common.subtotal') }}</td>
                                <td class="text-right">
                                    <span class="strong-600">{{ single_price($order->orderDetails->sum('price')) }}</span>
                                </td>
                            </tr>
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ __('storefront.checkout.shipping') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</span>
                                </td>
                            </tr> --}}
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ __('storefront.common.tax') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->orderDetails->sum('tax')) }}</span>
                                </td>
                            </tr> --}}
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ __('storefront.cart.coupon') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->coupon_discount) }}</span>
                                </td>
                            </tr> --}}
                            <tr>
                                <td class="w-50 fw-600">{{ __('storefront.common.total') }}</td>
                                <td class="text-right">
                                    <strong>{{ single_price($order->grand_total) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection

@section('modal')
    <!-- Product Review Modal -->
    <div class="modal fade" id="product-review-modal">
        <div class="modal-dialog">
            <div class="modal-content" id="product-review-modal-content">

            </div>
        </div>
    </div>
@endsection


@section('script')
    <script type="text/javascript">
        function product_review(product_id) {
            $.post('{{ route('product_review_modal') }}', {
                _token: '{{ @csrf_token() }}',
                product_id: product_id
            }, function(data) {
                $('#product-review-modal-content').html(data);
                $('#product-review-modal').modal('show', {
                    backdrop: 'static'
                });
                AIZ.extra.inputRating();
            });
        }
    </script>
@endsection
