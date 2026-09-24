@extends('backend.layouts.app')

@section('content')

<div class="card">
    <form class="" action="" id="sort_orders" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-md-0 h6">{{ translate('All Orders') }}</h5>
            </div>

            @can('delete_order')
                <div class="dropdown mb-2 mb-md-0">
                    <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                        {{translate('Bulk Action')}}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{translate('Delete selection')}}</a>
                    </div>
                </div>
            @endcan

            <div class="col-lg-2 ml-auto">
                <select class="form-control aiz-selectpicker" name="delivery_status" id="delivery_status">
                    <option value="">{{translate('Filter by Delivery Status')}}</option>
                    <option value="pending" @if ($delivery_status == 'pending') selected @endif>{{translate('Pending')}}</option>
                    <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>{{translate('Confirmed')}}</option>
                    {{-- <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>{{translate('Picked Up')}}</option>--}}
                    <option value="ready_for_delivery" @if ($delivery_status == 'ready_for_delivery') selected @endif>{{translate('Ready For Delivery')}}</option>
                    <option value="on_delivery" @if ($delivery_status == 'on_delivery') selected @endif>{{translate('On Delivery')}}</option>
                    <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>{{translate('Delivered')}}</option>
                    <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>{{translate('Cancel')}}</option>
                </select>
            </div>
            <div class="col-lg-2 ml-auto">
                <select class="form-control aiz-selectpicker" name="payment_status" id="payment_status">
                    <option value="">{{translate('Filter by Payment Status')}}</option>
                    <option value="paid"  @isset($payment_status) @if($payment_status == 'paid') selected @endif @endisset>{{translate('Paid')}}</option>
                    <option value="unpaid"  @isset($payment_status) @if($payment_status == 'unpaid') selected @endif @endisset>{{translate('Un-Paid')}}</option>
                </select>
              </div>
            <div class="col-lg-2">
                <div class="form-group mb-0">
                    <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off">
                </div>
            </div>
            <div class="col-lg-2">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type Order code & hit Enter') }}">
                </div>
            </div>
            <div class="col-auto">
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <!--<th>#</th>-->
                        @if(auth()->user()->can('delete_order'))
                            <th>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </th>
                        @else
                            <th data-breakpoints="lg">#</th>
                        @endif

                        <th>{{ translate('Order Code') }}</th>
                        <th data-breakpoints="xl">{{ translate('Num. of Products') }}</th>
                        <th data-breakpoints="xl">{{ translate('Otajer Order ID') }}</th>
                        <th data-breakpoints="xl">{{ translate('Company Name') }}</th>
                        <th data-breakpoints="xl">{{ translate('Order From') }}</th>
                        <th data-breakpoints="xl">{{ translate('Status') }}</th>
                        <th data-breakpoints="md">{{ translate('Customer') }}</th>
                        <th data-breakpoints="md">{{ translate('Representative') }}</th>
                        <th data-breakpoints="md">{{ translate('shipping_address') }}</th>
                        <th data-breakpoints="md">{{ translate('country') }}</th>
                        <th data-breakpoints="xl">{{ translate('phone') }}</th>
                        <th data-breakpoints="md">{{ translate('Customer Notes') }}</th>
                        <th data-breakpoints="md">{{ translate('Manager Notes') }}</th>
                        {{-- <th data-breakpoints="md">{{ translate('Seller') }}</th> --}}
                        <th data-breakpoints="md">{{ translate('Amount') }}</th>
                        <th data-breakpoints="md">{{ translate('Profit') }}</th>
                        <th data-breakpoints="md">{{ translate('Discount percent') }}</th>
                        <th data-breakpoints="md">{{ translate('Indicator') }}</th>
                        <th data-breakpoints="md">{{ translate('Delivery Status') }}</th>
                        <th data-breakpoints="md">{{ translate('Delivery Date') }}</th>
                        {{-- <th data-breakpoints="md">{{ translate('Payment method') }}</th>
                        <th data-breakpoints="md">{{ translate('Payment Status') }}</th> --}}
                        @if (addon_is_activated('refund_request'))
                        <th>{{ translate('Refund') }}</th>
                        @endif
                        <th class="text-right" width="15%">{{translate('options')}}</th>
                    </tr>
                </thead>
                <tbody>


                    @foreach ($orders as $key => $order)
                    <tr>
                        @if(auth()->user()->can('delete_order'))
                            <td>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-one" name="id[]" value="{{$order->id}}">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </td>
                        @else
                            <td>{{ ($key+1) + ($orders->currentPage() - 1)*$orders->perPage() }}</td>
                        @endif
                        <td>
                            {{ $order->code }}@if($order->viewed == 0) <span class="badge badge-inline badge-info">{{translate('New')}}</span>@endif
                        </td>
                        <td>
                            {{ count($order->orderDetails) }}
                        </td>

                        <td>
                            {{ $order->otajerOrderID }}
                        </td>

                        <td>
                            @isset($order->customer)
                            {{ $order->customer->company_name }}
                            @else
                            --
                            @endisset
                        </td>
                        <td>
                            @isset($order->order_from)
                            {{ $order->order_from }}
                            @else
                            --
                            @endisset
                        </td>

                        <td>
                            <span class="badge badge-inline badge-success"> {{ $order->wms_status  }}</span>
                        </td>


                        <td>
                            @if ($order->customer != null)
                                <a href="{{ route('customers.edit', $order->customer) }}">
                                    {{ $order->customer->name }}
                                </a>
                            @else
                                Deleted Account
                            @endif
                        </td>
                        <td>
                            @if ($order->by_rep)
                                <span class="badge badge-inline badge-success">{{ $order->user ? $order->user->name : "Deleted Account" }}</span>
                                @else
                                <span class="badge badge-inline badge-danger">{{translate('No Representative')}}</span>
                            @endif
                        </td>
                        @php
                            $shipping_address = json_decode($order->shipping_address, true);
                        @endphp
                        <td>
                            @if (!empty($shipping_address))
                                <a target="_blank"
                                    class="text-gray-800 text-hover-primary"
                                    href="https://www.google.com/maps?q={{ $shipping_address['address'] }}">
                                    {{ $shipping_address['address'] }}
                                </a>
                            @else
                                <span> {{ $order->customer?->shipping_address ?? '-' }} </span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($shipping_address))
                            <span>{{ $shipping_address['country'] }}</span>
                            @else
                            <span> - </span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($shipping_address))
                                <span>{{ $shipping_address['phone'] }}</span>
                            @else
                                <span> {{ $order->customer?->phone ?? '-' }}</span>
                            @endif
                        </td>
                        <td>
                            <span>{{ $order->additional_info }}</span>
                        </td>
                        <td>
                            <span id="manager-notes-{{ $order->id }}">{{ $order->manager_notes }}</span>
                        </td>
                        {{-- <td>
                            @if($order->shop)
                                {{ $order->shop->name }}
                            @else
                                {{ translate('Inhouse Order') }}
                            @endif
                        </td> --}}
                        <td>
                            {{ single_price($order->grand_total) }}
                        </td>
                        <td>
                            {{ single_price($order->profits) }}
                        </td>
                        <td>
                            {{round($order->discount_percent,2) }}
                        </td>
                        <td>
                            {{round($order->indicator,2) }}
                        </td>
                        <td>
                            @php
                                $status = $order->delivery_status;
                                $status_labels = [
                                    'pending' => ['label' => 'Pending', 'class' => 'badge-secondary'], // Gray
                                    'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-primary'], // Blue
                                    'ready_for_delivery' => ['label' => 'Ready For Delivery', 'class' => 'badge-warning'], // Yellow
                                    'on_delivery' => ['label' => 'On Delivery', 'class' => 'badge-info'], // Light Blue
                                    'delivered' => ['label' => 'Delivered', 'class' => 'badge-success'], // Green
                                    'canceled' => ['label' => 'Canceled', 'class' => 'badge-danger'], // Red
                                ];
                                $badgeClass = $status_labels[$status]['class'] ?? 'badge-dark'; // Default to dark if unknown
                                $statusText = translate(ucfirst(str_replace('_', ' ', $status)));
                            @endphp

                            <span class="badge badge-inline {{ $badgeClass }}">{{ $statusText }}</span>
                        </td>
                        <td>
                            @isset($order->delivery_date)
                            {{ date('d-m-Y', strtotime($order->delivery_date)) }}
                            @endisset
                        </td>
                        {{-- <td>
                            {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}
                        </td> --}}
                        {{-- <td>
                            @if ($order->payment_status == 'paid')
                            <span class="badge badge-inline badge-success">{{translate('Paid')}}</span>
                            @else
                            <span class="badge badge-inline badge-danger">{{translate('Unpaid')}}</span>
                            @endif
                        </td> --}}
                        @if (addon_is_activated('refund_request'))
                        <td>
                            @if (count($order->refund_requests) > 0)
                                {{ count($order->refund_requests) }} {{ translate('Refund') }}
                            @else
                                {{ translate('No Refund') }}
                            @endif
                        </td>
                        @endif
                        <td class="text-right">

                            @can('view_order_details')
                                @php
                                    $order_detail_route = route('orders.show', encrypt($order->id));
                                    if(Route::currentRouteName() == 'seller_orders.index') {
                                        $order_detail_route = route('seller_orders.show', encrypt($order->id));
                                    }
                                    else if(Route::currentRouteName() == 'pick_up_point.index') {
                                        $order_detail_route = route('pick_up_point.order_show', encrypt($order->id));
                                    }
                                    if(Route::currentRouteName() == 'inhouse_orders.index') {
                                        $order_detail_route = route('inhouse_orders.show', encrypt($order->id));
                                    }
                                @endphp
                                <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ $order_detail_route }}" title="{{ translate('View') }}">
                                    <i class="las la-eye"></i>
                                </a>
                            @endcan
                            <a href="#" onclick="openUpdateModal(this)" data-order-id="{{ $order->id }}" data-old-notes="{{ $order->manager_notes }}" class="btn btn-soft-warning btn-icon btn-circle btn-sm" title="{{ translate('Update Manager Notes') }}">
                                <i class="las la-edit"></i>
                            </a>
                            <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="{{ route('invoice.download', $order->id) }}" title="{{ translate('Download Invoice') }}">
                                <i class="las la-download"></i>
                            </a>
                            @can('delete_order')
                                <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('orders.destroy', $order->id)}}" title="{{ translate('Delete') }}">
                                    <i class="las la-trash"></i>
                                </a>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="aiz-pagination">
                {{ $orders->appends(request()->input())->links() }}
            </div>

        </div>
    </form>
</div>

@endsection

@section('modal')
    <!-- update notes Modal -->
    <div id="update-modal" class="modal fade">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title h6">{{translate('Update Manager Notes')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body text-center">
                    <input type="hidden" id="order-id">
                    <textarea id="update-notes" class="form-control" rows="4" value=""></textarea>
                    <button type="button" class="btn btn-secondary rounded-0 mt-2" data-dismiss="modal">{{translate('Cancel')}}</button>
                    <button type="button" class="btn btn-primary rounded-0 mt-2" onclick="update_notes()">{{translate('Update')}}</button>
                </div>
            </div>
        </div>
    </div><!-- /.modal -->
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if(this.checked) {
                // Iterate each checkbox
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }

        });

//        function change_status() {
//            var data = new FormData($('#order_form')[0]);
//            $.ajax({
//                headers: {
//                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
//                },
//                url: "{{route('bulk-order-status')}}",
//                type: 'POST',
//                data: data,
//                cache: false,
//                contentType: false,
//                processData: false,
//                success: function (response) {
//                    if(response == 1) {
//                        location.reload();
//                    }
//                }
//            });
//        }

        function bulk_delete() {
            var data = new FormData($('#sort_orders')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('bulk-order-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response == 1) {
                        location.reload();
                    }
                }
            });
        }
    </script>
    <script>
        function update_notes(id){
            var updatedNotes = document.getElementById('update-notes').value;
            var orderId = document.getElementById('order-id').value;
            $('#update-modal').modal('hide');
            $.post('{{ route('orders.update_manager_notes') }}', {_token:'{{ csrf_token() }}', id:orderId, notes:updatedNotes }, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', '{{ translate('Notes updated successfully') }}');
                    var updateButton = document.querySelector('[data-order-id="' + orderId + '"]');
                    updateButton.setAttribute('data-old-notes', updatedNotes);
                    $('#manager-notes-' + orderId).text(updatedNotes);
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function openUpdateModal(button) {
            var orderId = button.getAttribute('data-order-id');
            var oldNotes = button.getAttribute('data-old-notes');
            document.getElementById('update-notes').value = oldNotes;
            document.getElementById('order-id').value = orderId;
            $('#update-modal').modal('show');
        }
    </script>

@endsection
