@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <form class="" action="" id="sort_orders" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-md-0 h6">{{ translate('All Orders') }}</h5>
                </div>

                <div class="col-auto">
                    <div class="form-group mb-0">
                        <a href="{{ route('new.feat-order.trip.create') }}"
                            class="btn btn-primary">{{ translate('Create Order') }}</a>
                    </div>
                </div>

                <div class="col-lg-2 ml-auto">
                    <form method="GET" action="{{ route('trips.orders') }}" id="delivery-status-form">
                        <div class="aiz-checkbox-inline">
                            <label class="aiz-checkbox">
                                <input type="radio" name="delivery_status" value="confirmed" o
                                    mnhbgnchange="this.form.submit()"
                                    {{ $delivery_status == 'confirmed' ? 'checked' : '' }}>
                                {{ translate('Confirmed') }}
                                <span class="aiz-square-check"></span>
                            </label>
                        </div>

                        <div class="aiz-checkbox-inline">
                            <label class="aiz-checkbox">
                                <input type="radio" name="delivery_status" value="ready_for_delivery"
                                    onchange="this.form.submit()"
                                    {{ $delivery_status == 'ready_for_delivery' ? 'checked' : '' }}>
                                {{ translate('Ready For Delivery') }}
                                <span class="aiz-square-check"></span>
                            </label>
                        </div>

                        <div class="aiz-checkbox-inline">
                            <label class="aiz-checkbox">
                                <input type="radio" name="delivery_status" value="on_delivery"
                                    onchange="this.form.submit()" {{ $delivery_status == 'on_delivery' ? 'checked' : '' }}>
                                {{ translate('On Delivery') }}
                                <span class="aiz-square-check"></span>
                            </label>
                        </div>

                        <div class="aiz-checkbox-inline">
                            <label class="aiz-checkbox">
                                <input type="radio" name="delivery_status" value="delivered" onchange="this.form.submit()"
                                    {{ $delivery_status == 'delivered' ? 'checked' : '' }}>
                                {{ translate('Delivered') }}
                                <span class="aiz-square-check"></span>
                            </label>
                        </div>
                    </form>
                </div>
                @can('delete_order')
                    <div class="dropdown mb-2 mb-md-0">
                        <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                            {{ translate('Bulk Action') }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" onclick="bulk_delete()">
                                {{ translate('Delete selection') }}</a>
                            <a class="dropdown-item" href="#" onclick="bulk_change_delivery_status_to_confirmed()">
                                {{ translate('Mark As Confirmed') }}</a>
                            <a class="dropdown-item" href="#"
                                onclick="bulk_change_delivery_status_to_ready_for_delivery()">
                                {{ translate('Mark As Ready For Delivery') }}</a>
                        </div>
                    </div>
                @endcan
                <div class="col-lg-2">
                    <div class="form-group mb-0">
                        <input type="text" class="aiz-date-range form-control" value="{{ $date }}"
                            name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y"
                            data-separator=" to " data-advanced-range="true" autocomplete="off">
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type Order code & hit Enter') }}">
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
                            @if (auth()->user()->can('delete_order'))
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
                            <th data-breakpoints="xl">{{ translate('Otajer Customer ID') }}</th>
                            <th data-breakpoints="xl">{{ translate('Company Name') }}</th>
                            <th data-breakpoints="xl">{{ translate('Order From') }}</th>
                            <th data-breakpoints="xl">{{ translate('Status') }}</th>
                            <th data-breakpoints="md">{{ translate('Customer') }}</th>
                            <th>{{ translate('Trip Code') }}</th>
                            <th data-breakpoints="md">{{ translate('Driver') }}</th>
                            <th data-breakpoints="md">{{ translate('Truck') }}</th>
                            <th data-breakpoints="md">{{ translate('Representative') }}</th>
                            <th data-breakpoints="md">{{ translate('shipping_address') }}</th>
                            <th data-breakpoints="md">{{ translate('country') }}</th>
                            <th data-breakpoints="xl">{{ translate('phone') }}</th>
                            <th data-breakpoints="l">{{ translate('Invoice Number') }}</th>
                            <th data-breakpoints="md">{{ translate('Customer Notes') }}</th>
                            <th data-breakpoints="md">{{ translate('Manager Notes') }}</th>
                            <th data-breakpoints="md">{{ translate('Amount') }}</th>
                            <th data-breakpoints="md">{{ translate('Delivery Status') }}</th>
                            <th data-breakpoints="md">{{ translate('Delivery Date') }}</th>
                            <th data-breakpoints="xl">{{ translate('Photos') }}</th>
                            <th class="text-right" width="15%">{{ translate('options') }}</th>
                        </tr>
                    </thead>
                    <tbody>


                        @foreach ($orders as $key => $order)
                            @php
                                $checkpoint = $order->checkpoints?->last();
                            @endphp
                            <tr>
                                @if (auth()->user()->can('delete_order'))
                                    <td>
                                        <div class="form-group">
                                            <div class="aiz-checkbox-inline">
                                                <label class="aiz-checkbox">
                                                    <input type="checkbox" class="check-one" name="id[]"
                                                        value="{{ $order->id }}">
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td>{{ $key + 1 + ($orders->currentPage() - 1) * $orders->perPage() }}</td>
                                @endif
                                <td>
                                    {{ $order->code }}@if ($order->viewed == 0)
                                        <span class="badge badge-inline badge-info">{{ translate('New') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ count($order->orderDetails) }}
                                </td>

                                <td>
                                    {{ $order->otajerOrderID }}
                                </td>

                                <td>
                                    @isset($order->customer)
                                        {{ $order->customer->AccSysID }}
                                    @else
                                        --
                                    @endisset
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
                                    <span class="badge badge-inline badge-success"> {{ $order->wms_status }}</span>
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
                                    @if ($checkpoint?->trip)
                                        {{ $checkpoint->trip->code }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($checkpoint?->trip?->driver)
                                        {{ $checkpoint->trip?->driver->name }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($checkpoint?->trip?->truck)
                                        {{ $checkpoint->trip?->truck->name }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($order->by_rep)
                                        <span
                                            class="badge badge-inline badge-success">{{ $order->user ? $order->user->name : 'Deleted Account' }}</span>
                                    @else
                                        <span
                                            class="badge badge-inline badge-danger">{{ translate('No Representative') }}</span>
                                    @endif
                                </td>
                                @php
                                    $shipping_address = json_decode($order->shipping_address, true);
                                @endphp
                                <td>
                                    @if (!empty($shipping_address))
                                        <a target="_blank" class="text-gray-800 text-hover-primary"
                                            href="https://www.google.com/maps?q={{ $shipping_address['address'] }}">
                                            {{ $shipping_address['address'] }}
                                        </a>
                                    @elseif($order->customer?->shipping_address)
                                        <a target="_blank" class="text-gray-800 text-hover-primary"
                                            href="https://www.google.com/maps?q={{ $order->customer?->shipping_address }}">
                                            {{ $order->customer?->shipping_address }}
                                        </a>
                                    @else
                                        <span> - </span>
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
                                    <textarea type="text" class="form-control invoice-number" style="width: 7rem;"
                                        data-order-id="{{ $order->id }}">{{ $order->invoice_number }}</textarea>
                                </td>
                                <td>
                                    <span>{{ $order->additional_info }}</span>
                                </td>
                                <td>
                                    <span id="manager-notes-{{ $order->id }}">{{ $order->manager_notes }}</span>
                                </td>

                                <td>
                                    {{ single_price($order->grand_total) }}
                                </td>
                                <td>
                                    @php
                                        $status = $order->delivery_status;
                                        $status_labels = [
                                            'pending' => ['label' => 'Pending', 'class' => 'badge-secondary'], // Gray
                                            'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-primary'], // Blue
                                            'ready_for_delivery' => [
                                                'label' => 'Ready For Delivery',
                                                'class' => 'badge-warning',
                                            ], // Yellow
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
                                <td>
                                    @if ($checkpoint)
                                        @forelse (get_images_path($checkpoint->photos) as $photo_link)
                                            <img src="{{ $photo_link }}" class="mx-1" alt="image" width="100"
                                                height="100">
                                        @empty
                                        @endforelse
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($checkpoint)
                                        <a href="{{ route('checkpoints.print-cmr', $checkpoint->id) }}"
                                            class="btn btn-soft-info btn-icon btn-circle btn-sm"
                                            title="{{ translate('Print CMR') }}">
                                            <i class="las la-print"></i>
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-soft-danger btn-icon btn-circle btn-sm"
                                        onclick="updateOrderNotes(this)" title="{{ translate('Change status') }}"
                                        data-order-id="{{ $order->id }}">
                                        <i class="las la-edit"></i>
                                    </button>

                                    <a class="" href="{{route('new.feat-order.trip.edit', $order->id)}}">{{ translate('Edit') }}</a>
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
                    <h4 class="modal-title h6">{{ translate('Update Manager Notes') }}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body text-center">
                    <input type="hidden" id="order-id">
                    <textarea id="update-notes" class="form-control" rows="4" value=""></textarea>
                    <button type="button" class="btn btn-secondary rounded-0 mt-2"
                        data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="button" class="btn btn-primary rounded-0 mt-2"
                        onclick="update_notes()">{{ translate('Update') }}</button>
                </div>
            </div>
        </div>
    </div><!-- /.modal -->
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if (this.checked) {
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

        function bulk_delete() {
            var data = new FormData($('#sort_orders')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('bulk-order-delete') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }

        function bulk_change_delivery_status_to_confirmed() {
            var data = new FormData($('#sort_orders')[0]);
            data.append('delivery_status', 'confirmed');

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('trips.orders.bulk-change-delivery-status') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }

        function bulk_change_delivery_status_to_ready_for_delivery() {
            var data = new FormData($('#sort_orders')[0]);
            data.append('delivery_status', 'ready_for_delivery');

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('trips.orders.bulk-change-delivery-status') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }
    </script>

    <script>
        function update_notes(id) {
            var updatedNotes = document.getElementById('update-notes').value;
            var orderId = document.getElementById('order-id').value;
            $('#update-modal').modal('hide');
            $.post('{{ route('orders.update_manager_notes') }}', {
                _token: '{{ csrf_token() }}',
                id: orderId,
                notes: updatedNotes
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Notes updated successfully') }}');
                    var updateButton = document.querySelector('[data-order-id="' + orderId + '"]');
                    updateButton.setAttribute('data-old-notes', updatedNotes);
                    $('#manager-notes-' + orderId).text(updatedNotes);
                } else {
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

        $(document).on("change", ".invoice-number", function() {

            var inputField = $(this);
            var orderId = inputField.data("order-id");
            var newInvoiceNumber = inputField.val();

            $.ajax({
                url: "{{ route('trips.orders.update_invoice_number') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    order_id: orderId,
                    invoice_number: newInvoiceNumber
                },
                success: function(response) {
                    if (response.success) {
                        inputField.css("border", "2px solid green"); // Highlight on success
                        AIZ.plugins.notify('success',
                            "{{ translate('Invoice updated successfully') }}");
                    } else {
                        inputField.css("border", "2px solid red"); // Highlight on error
                        AIZ.plugins.notify('danger', "{{ translate('Error updating invoice') }}");
                    }
                },
                error: function() {
                    inputField.css("border", "2px solid red"); // Highlight on error
                    AIZ.plugins.notify('danger', "{{ translate('Something went wrong') }}");
                }
            });
        });

        function updateOrderNotes(e) {
            console.log(123123231123);
            var orderId = $(e).data('order-id');

            $.ajax({
                url: '{{ route('trips.orders.update_order_status_to_delivered') }}',
                type: 'POST',
                data: {
                    order_id: orderId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to update order status.');
                    }
                },
                error: function() {
                    alert('An error occurred.');
                }
            });
        }
    </script>
@endsection
