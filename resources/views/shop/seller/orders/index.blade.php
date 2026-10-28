@extends('shop.seller.layouts.app')

@section('panel_content')

    <div class="card">
        <form id="sort_orders" action="" method="GET">
          <div class="card-header row gutters-5">
            <div class="col text-center text-md-left">
              <h5 class="mb-md-0 h6">{{ __('seller.dashboard.orders') }}</h5>
            </div>
              <div class="col-md-3 ml-auto">
                  <select class="form-control aiz-selectpicker" data-placeholder="{{ __('seller.orders.filter_by_payment_status')}}" name="payment_status" onchange="sort_orders()">
                      <option value="">{{ __('seller.orders.filter_by_payment_status')}}</option>
                      <option value="paid" @isset($payment_status) @if($payment_status == 'paid') selected @endif @endisset>{{ __('storefront.status.paid')}}</option>
                      <option value="unpaid" @isset($payment_status) @if($payment_status == 'unpaid') selected @endif @endisset>{{ __('storefront.status.unpaid')}}</option>
                  </select>
              </div>

              <div class="col-md-3 ml-auto">
                <select class="form-control aiz-selectpicker" data-placeholder="{{ __('seller.orders.filter_by_payment_status')}}" name="delivery_status" onchange="sort_orders()">
                    <option value="">{{ __('seller.orders.filter_by_deliver_status')}}</option>
                    <option value="pending" @isset($delivery_status) @if($delivery_status == 'pending') selected @endif @endisset>{{ __('storefront.status.pending')}}</option>
                    <option value="confirmed" @isset($delivery_status) @if($delivery_status == 'confirmed') selected @endif @endisset>{{ __('storefront.status.confirmed')}}</option>
                    <option value="on_delivery" @isset($delivery_status) @if($delivery_status == 'on_delivery') selected @endif @endisset>{{ __('storefront.status.on_delivery')}}</option>
                    <option value="delivered" @isset($delivery_status) @if($delivery_status == 'delivered') selected @endif @endisset>{{ __('storefront.status.delivered')}}</option>
                </select>
              </div>
              <div class="col-md-3">
                <div class="from-group mb-0">
                    <input type="text" class="form-control" id="search" name="search" @isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ __('seller.orders.type_order_code_hit_enter') }}">
                </div>
              </div>
          </div>
        </form>

        @if (count($orders) > 0)
            <div class="card-body p-3">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('storefront.checkout.order_code_label')}}</th>
                            <th data-breakpoints="lg">{{ __('seller.orders.num_of_products')}}</th>
                            <th data-breakpoints="lg">{{ __('storefront.common.customer')}}</th>
                            <th data-breakpoints="md">{{ __('storefront.common.amount')}}</th>
                            <th data-breakpoints="lg">{{ __('storefront.checkout.delivery_status')}}</th>
                            <th>{{ __('storefront.checkout.payment_status')}}</th>
                            <th class="text-right">{{ __('storefront.common.options')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $key => $order_id)
                            @php
                                $order = \App\Models\Order::find($order_id->id);
                            @endphp
                            @if($order != null)
                                <tr>
                                    <td>
                                        {{ $key+1 }}
                                    </td>
                                    <td>
                                        <a href="#{{ $order->code }}" onclick="show_order_details({{ $order->id }})">{{ $order->code }}</a>
                                    </td>
                                    <td>
                                        {{ count($order->orderDetails->where('seller_id', Auth::user()->id)) }}
                                    </td>
                                    <td>
                                        @if ($order->user_id != null)
                                            {{ optional($order->user)->name }}
                                        @else
                                            {{ __('seller.orders.guest') }} ({{ $order->guest_id }})
                                        @endif
                                    </td>
                                    <td>
                                        {{ single_price($order->grand_total) }}
                                    </td>
                                    <td>
                                        @php
                                            $status = $order->delivery_status;
                                        @endphp
                                        {{ \App\Support\StoreLocale::status($status) }}
                                    </td>
                                    <td>
                                        @if ($order->payment_status == 'paid')
                                            <span class="badge badge-inline badge-success">{{ __('storefront.status.paid')}}</span>
                                        @else
                                            <span class="badge badge-inline badge-danger">{{ __('storefront.status.unpaid')}}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('seller.orders.show', encrypt($order->id)) }}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ __('storefront.checkout.order_details') }}">
                                            <i class="las la-eye"></i>
                                        </a>
                                        <a href="{{ route('seller.invoice.download', $order->id) }}" class="btn btn-soft-warning btn-icon btn-circle btn-sm" title="{{ __('storefront.checkout.download_invoice') }}">
                                            <i class="las la-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $orders->links() }}
              	</div>
            </div>
        @endif
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        function sort_orders(el){
            $('#sort_orders').submit();
        }
    </script>
@endsection
