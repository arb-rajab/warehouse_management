@extends('frontend.layouts.user_panel')

@section('panel_content')
    <!-- Order id -->
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ translate('Order id') }}: {{ $order->code }}</h1>
            </div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="card rounded-0 shadow-none border mb-4">
        <div class="card-header border-bottom-0">
            <h5 class="fs-16 fw-700 text-dark mb-0">{{ translate('Order Summary') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">

                <div class="col-lg-6">
                    <table class="table-borderless table">
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Order Code') }}:</td>
                            <td>{{ $order->code }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-800">{{ translate('Order By') }}:</td>
                            <td class="fw-800">{{ json_decode($order->shipping_address)->name }}</td>
                        </tr>

                        @if($order->user_id != null && ! $order->by_rep)
                        <tr>
                            <td class="w-50 fw-800">{{ translate('Customer Otajer ID') }}:</td>
                            <td class="fw-800"> {{ $order->user->AccSysID }}</td>
                        </tr>
                        @endif

                        <tr>
                            <td class="w-50 fw-600">{{ translate('Email') }}:</td>
                            @if ($order->user_id != null)
                                <td>{{ $order->user->email }}</td>
                            @endif
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Shipping address') }}:</td>
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
                            <td class="w-50 fw-600">{{ translate('Order date') }}:</td>
                            <td>{{ date('d-m-Y H:i A', $order->date) }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Order status') }}:</td>
                            <td>{{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}</td>
                        </tr>
                        @if(Auth::check() && Auth::user()->admin_verified)
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Total order amount') }}:</td>
                            <td>{{ single_price($order->orderDetails->sum('price')) }}
                            </td>
                        </tr>
                        @endif
                        {{--
                        <tr>
                            <td class="w-50 fw-600">{{ translate('Shipping method') }}:</td>
                            <td>{{ translate('Flat shipping rate') }}</td>
                        </tr>

                        <tr>
                            <td class="w-50 fw-600">{{ translate('Payment method') }}:</td>
                            <td>{{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</td>
                        </tr>
                         --}}
                        <tr>
                            <td class="text-main w-50 fw-800">{{ translate('Order Notes') }}</td>
                            <td class="fw-800">{{ $order->additional_info }}</td>
                        </tr>
                        @if ($order->by_rep)
                            <tr>
                                <td class="text-main fw-800">{{ translate('By Representative :') }}</td>
                                <td class="fw-800">{{ $order->user->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-main fw-800">{{ translate('For Customer :') }}</td>
                                <td class="fw-800">{{ $order->rep->name }}</td>
                            </tr>

                            <tr>
                                <td class="text-main fw-800">{{ translate('Customer Otajer ID:') }}</td>
                                <td class="fw-800">{{ $order->rep->AccSysID }}</td>
                            </tr>
                        @endif
                        @if ($order->tracking_code)
                            <tr>
                                <td class="w-50 fw-600">{{ translate('Tracking code') }}:</td>
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
                    <h5 class="fs-16 fw-700 text-dark mb-0">{{ translate('Order Details') }}</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="aiz-table table">
                        <thead class="text-gray fs-12">
                            <tr>
                                <th class="pl-0">#</th>
                                <th width="30%">{{ translate('Product') }}</th>
                                <th data-breakpoints="md">{{ translate('Unit') }}</th>
                                <th data-breakpoints="md">{{ translate('Variation') }}</th>

                                {{-- <th data-breakpoints="md">{{ translate('Delivery Type') }}</th> --}}
                                <th>{{ translate('Item Notes') }}</th>
                                <th>{{ translate('Quantity') }}</th>
                                @if(Auth::check() && Auth::user()->admin_verified)
                                <th>{{ translate('Price') }}</th>
                                @endif
                                @if (addon_is_activated('refund_request'))
                                    <th data-breakpoints="md">{{ translate('Refund') }}</th>
                                @endif
                                @if(get_setting("Product_Reviews_Section") == 1)
                                <th data-breakpoints="md" class="text-right pr-0">{{ translate('Review') }}</th>
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
                                            <a href="{{ route('product', $orderDetail->product->slug) }}"
                                                target="_blank">{{ $orderDetail->product->getTranslation('name') }}</a>
                                        @elseif($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                target="_blank">{{ $orderDetail->product->getTranslation('name') }}</a>
                                        @else
                                            <strong>{{ translate('Product Unavailable') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $orderDetail->variation }}
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
                                            {{ translate('Standard Delivery') }}
                                        @elseif ($order->shipping_type == 'pickup_point')
                                            @if ($order->pickup_point != null)
                                                {{ $order->pickup_point->name }} ({{ translate('Pickip Point') }})
                                            @else
                                                {{ translate('Pickup Point') }}
                                            @endif
                                        @elseif($order->shipping_type == 'carrier')
                                            @if ($order->carrier != null)
                                                {{ $order->carrier->name }} ({{ translate('Carrier') }})
                                                <br>
                                                {{ translate('Transit Time').' - '.$order->carrier->transit_time }}
                                            @else
                                                {{ translate('Carrier') }}
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
                                                    class="btn btn-primary btn-sm rounded-0">{{ translate('Send') }}</a>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 0)
                                                <b class="text-info">{{ translate('Pending') }}</b>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 2)
                                                <b class="text-success">{{ translate('Rejected') }}</b>
                                            @elseif ($orderDetail->refund_request != null && $orderDetail->refund_request->refund_status == 1)
                                                <b class="text-success">{{ translate('Approved') }}</b>
                                            @elseif ($orderDetail->product->refundable != 0)
                                                <b>{{ translate('N/A') }}</b>
                                            @else
                                                <b>{{ translate('Non-refundable') }}</b>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="text-xl-right pr-0">
                                        @if(get_setting("Product_Reviews_Section") == 1)
                                        @if ($orderDetail->delivery_status == 'delivered')
                                            <a href="javascript:void(0);"
                                                onclick="product_review('{{ $orderDetail->product_id }}')"
                                                class="btn btn-primary btn-sm rounded-0"> {{ translate('Review') }} </a>
                                        @else
                                            <span class="text-danger">{{ translate('Not Delivered Yet') }}</span>
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
                    <b class="fs-16 fw-700 text-dark">{{ translate('Order Ammount') }}</b>
                </div>
                <div class="card-body pb-0">
                    <table class="table-borderless table">
                        <tbody>
                            <tr>
                                <td class="w-50 fw-600">{{ translate('Subtotal') }}</td>
                                <td class="text-right">
                                    <span class="strong-600">{{ single_price($order->orderDetails->sum('price')) }}</span>
                                </td>
                            </tr>
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ translate('Shipping') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</span>
                                </td>
                            </tr> --}}
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ translate('Tax') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->orderDetails->sum('tax')) }}</span>
                                </td>
                            </tr> --}}
                            {{-- <tr>
                                <td class="w-50 fw-600">{{ translate('Coupon') }}</td>
                                <td class="text-right">
                                    <span class="text-italic">{{ single_price($order->coupon_discount) }}</span>
                                </td>
                            </tr> --}}
                            <tr>
                                <td class="w-50 fw-600">{{ translate('Total') }}</td>
                                <td class="text-right">
                                    <strong>{{ single_price($order->grand_total) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($order->manual_payment && $order->manual_payment_data == null)
                <button onclick="show_make_payment_modal({{ $order->id }})"
                    class="btn btn-block btn-primary">{{ translate('Make Payment') }}</button>
            @endif
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

    <!-- Payment Modal -->
    <div class="modal fade" id="payment_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div id="payment_modal_body">

                </div>
            </div>
        </div>
    </div>
@endsection


@section('script')
    <script type="text/javascript">
        function show_make_payment_modal(order_id) {
            $.post('{{ route('checkout.make_payment') }}', {
                _token: '{{ csrf_token() }}',
                order_id: order_id
            }, function(data) {
                $('#payment_modal_body').html(data);
                $('#payment_modal').modal('show');
                $('input[name=order_id]').val(order_id);
            });
        }

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
