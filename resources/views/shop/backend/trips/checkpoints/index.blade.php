@extends('shop.backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate("Trip's Checkpoints") }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_checkpoints" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('checkpoints') . ' | ' . $trip->driver?->name }}</h5>
                </div>
                <div class="d-flex justify-content-end mb-3">
                    <button onclick="printPage()" class="btn btn-primary">
                        <i class="las la-print"></i> {{ translate('Print') }}
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="row gutters-5">
                    <div class="col text-md-left text-center">
                        {{-- @php
                            $address = json_decode($checkpoint->shipping_address);
                        @endphp
                        @if ($address)
                            <address>
                                <strong class="text-main">
                                    {{ $address->name }}
                                </strong><br>
                                {{ $address->email }}<br>
                                {{ $address->phone }}<br>
                                {{ $address->address }},
                                {{ $address->city }}, @if (isset($address->state))
                                    {{ $address->state }} -
                                @endif {{ $address->postal_code }}<br>
                                {{ $address->country }}
                            </address>
                        @else
                            <address>
                                <strong class="text-main">
                                    {{ $checkpoint->user->name }}
                                </strong><br>
                                {{ $checkpoint->user->email }}<br>
                                {{ $checkpoint->user->phone }}<br>
                            </address>
                        @endif --}}

                    </div>


                    {{-- <div class="col text-md-left text-center">
                        <p>{{ translate('Company Address') }}:</p>
                        <p>{{ $order->company_address }}</p>
                        <p>{{ translate('Company Shipping Address') }}:</p>
                        <p>{{ $order->company_shipping_address }}</p>
                        <p>{{ translate('Tax Number') }}:</p>
                        <p>{{ $order->company_tax_number }}</p>
                    </div> --}}
                </div>

                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Customer Name') }}</th>
                            <th>{{ translate('Rep Name') }}</th>
                            <th>{{ translate('Order Code') }}</th>
                            <th>{{ translate('Postal Code') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Arrived At') }}</th>
                            <th>{{ translate('Estimated Time') }}</th>
                            <th>{{ translate('Notes') }}</th>
                            <th>{{ translate('phone') }}</th>
                            <th>{{ translate('Num. of Products') }}</th>
                            <th>{{ translate('Company Name') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Invoice Number') }}</th>
                            <th>{{ translate('Photos') }}</th>
                            <th>{{ translate('CMR') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checkpoints as $key => $checkpoint)
                            <tr>
                                <td>
                                    {{  $checkpoint->getCustomerForCheckpoint()?->name }}
                                </td>
                                <td>
                                    @if ($checkpoint->isOrder())
                                        <p>{{ translate("Order") }}: {{ $checkpoint->getOrder()->representative?->name }}</p>
                                    @else
                                    <span> - </span>
                                    @endif

                                </td>
                                <td>
                                    @if ($checkpoint->isOrder())
                                        <p>{{ translate("Order") }}: {{ $checkpoint->getOrder()->code }}</p>
                                    @endif
                                    @if ($checkpoint->isCustomer())
                                        <p>{{ translate("Customer Checkpoint") }} </p>
                                    @endif
                                </td>

                                <td>{{ $checkpoint->getCustomerForCheckpoint()?->shipping_address }}</td>

                                <td>
                                    <span id="checkpoint-status-{{ $checkpoint->id }}">{{ translate($checkpoint->status) }}</span>
                                    @can('edit_trip')
                                    <a href="#" onclick="openUpdateModal(this)" data-checkpoint-id="{{ $checkpoint->id }}" data-old-status="{{ $checkpoint->status }}" class="btn btn-soft-warning btn-icon btn-circle btn-sm" title="{{ translate('Update Status') }}">
                                        <i class="las la-edit"></i>
                                    </a>
                                    @endcan
                                </td>

                                <td>
                                    @if ($checkpoint->arrived_at)
                                        <span class="badge badge-inline badge-light">
                                            {{ Carbon::parse($checkpoint->arrived_at)->format('Y-m-d H:i') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge badge-inline badge-success">
                                        {{ Carbon::now()->addSeconds($checkpoint->eta)->locale('en')->diffForHumans(null, true, true, 2) ?? 'N/A' }}
                                    </span>
                                </td>

                                <td>
                                    @can('edit_trip')
                                    <textarea type="text" class="form-control checkpoint-notes" style="width: 7rem;" data-checkpoint-id="{{ $checkpoint->id }}">{{ $checkpoint->notes }}</textarea>
                                    @else
                                    {{ $checkpoint->notes }}
                                    @endcan
                                </td>

                                <td>{{ $checkpoint->getCustomerForCheckpoint()?->phone ?? '-' }}</td>

                                <td>
                                    @if ($checkpoint->isOrder())
                                        <p>{{ count($checkpoint->getOrder()->orderDetails) }}</p>
                                    @else
                                    -
                                    @endif
                                </td>

                                <td>{{ $checkpoint->getCustomerForCheckpoint()?->company_name }}</td>


                                <td>
                                    @if ($checkpoint->isOrder())
                                        <p>{{ single_price($checkpoint->getOrder()->grand_total) }}</p>
                                    @else
                                    -
                                    @endif
                                </td>

                                <td>
                                    @if ($checkpoint->isOrder())
                                        <p>{{ $checkpoint->getOrder()->invoice_number }}</p>
                                    @else
                                    -
                                    @endif
                                </td>

                                <td>
                                    @forelse (get_images_path($checkpoint->photos) as $photo_link)
                                        <img src="{{ $photo_link }}" class="mx-1" alt="image" width="100" height="100">
                                    @empty
                                    @endforelse
                                </td>

                                <td>
                                    @if ($checkpoint->cmr_file)
                                        <a href="{{ route('checkpoints.print-cmr', $checkpoint->id) }}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ translate('Print CMR') }}">
                                            <i class="las la-print"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
            @if($checkpoints->count() > 0)
                <div class="row">
                    <div class="col-lg-12">
                        <div id="map" style="height: 30rem; width: auto; border:0; margin: auto;"></div>
                    </div>
                </div>
            @else
                <div class="row">
                    <div class="col-lg-12 text-center">
                        <div class="alert alert-warning" role="alert">
                            <h2><i class="fas fa-exclamation-circle"></i> {{ translate('No Checkpoints found on this Trip') }}</h2>
                            {{-- <p>{{ translate('Please try selecting a different date.') }}</p> --}}
                        </div>
                    </div>
                </div>
            @endif
        </form>
    </div>
@endsection

@section('modal')
    @can('edit_trip')
    @include('shop.backend.trips.components.update-checkpoint-status-modal')
    @endcan
    @include('shop.modals.delete_modal')
@endsection

@section('style')
    <style>
        .custom-marker {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            color: #fff;
            font-weight: bold;
        }
        .marker-label {
            font-size: 15px;
            border-radius: 50%;
            text-align: center;
        }
        .custom-marker.red {
            background-color: #ff0000;
        }
        .custom-marker.blue {
            background-color: #007bff;
        }

        .sort-order-label {
            background-color: white;
            border: 1px solid black;
            border-radius: 5px;
            padding: 2px 5px;
            font-size: 12px;
            text-align: center;
        }

        @media print {
            body * {
                visibility: hidden; /* Hide everything by default */
            }

            .aiz-titlebar, .card, .card-body, .table, .table thead, .table tbody, .table tr, .table td, .table th {
                visibility: visible; /* Show the main content */
            }

            .card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .print-mode button {
                display: none !important; /* Hide buttons in print mode */
            }
        }

    </style>
@endsection

@section('script')
    @include('shop.backend.trips.inc.map-popups')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <script>
        if (document.getElementById('map')) {
        // Marker data passed from PHP
        var markers = @json($long_lat_array);

        // Route coordinates passed from PHP
        var routeCoordinates = @json($coordinates);

        var firstPoint = markers.map(function(marker) {
            return tripMapCoordinates(marker.latitude, marker.longitude);
        }).find(function(point) { return point !== null; });
        var map = L.map('map').setView(firstPoint || [0, 0], 2);

        // Add the base OSM tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Add markers to the map and adjust bounds
        var latLngBounds = new L.LatLngBounds();

        markers.forEach(function(marker) {
            const point = tripMapCoordinates(marker.latitude, marker.longitude);
            if (point !== null) {
                // Define a custom marker icon (optional)
                var customIcon = L.divIcon({ className: 'custom-marker blue' });

                // Add the marker to the map
                L.marker(point).addTo(map)
                    .bindPopup(tripMapPopup([[null, marker.label]]));

                // Extend the map bounds to include the marker
                latLngBounds.extend(point);
            }
        });

        // Convert GeoJSON coordinates to Leaflet format ([lat, lon]) for the route
        var routeLatLngs = routeCoordinates.map(coord => [coord[1], coord[0]]);

        // Draw the route on the map if routeCoordinates exist
        if (routeLatLngs.length > 0) {
            var routePolyline = L.polyline(routeLatLngs, { color: 'blue', weight: 4 }).addTo(map);

            // Extend bounds to include the route
            routeLatLngs.forEach(coord => latLngBounds.extend(coord));
        }

        // Fit the map to the LatLngBounds with some padding if there are markers or a route
        if (latLngBounds.isValid()) {
            map.fitBounds(latLngBounds, { padding: [2, 2] });
            map.setZoom(map.getZoom() - 1); // Decrease zoom level slightly for better fit
        } else {
            console.warn('No valid markers or routes to display on the map.');
        }

        }

        $(document).on("change", ".checkpoint-notes", function() {
            var inputField = $(this);
            var checkpointId = inputField.data("checkpoint-id");
            var newNotes = inputField.val();

            $.ajax({
                url: "{{ route('checkpoints.update-notes') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    checkpoint_id: checkpointId,
                    notes: newNotes
                },
                success: function(response) {
                    if (response.success) {
                        inputField.css("border", "2px solid green"); // Highlight on success
                        AIZ.plugins.notify('success',
                            "{{ translate('Notes updated successfully') }}");
                    } else {
                        inputField.css("border", "2px solid red"); // Highlight on error
                        AIZ.plugins.notify('danger', "{{ translate('Error updating Notes') }}");
                    }
                },
                error: function() {
                    inputField.css("border", "2px solid red"); // Highlight on error
                    AIZ.plugins.notify('danger', "{{ translate('Something went wrong') }}");
                }
            });
        });
    </script>
    <script>
        function printPage() {
            document.body.classList.add('print-mode');
            window.print();
            document.body.classList.remove('print-mode');
        }
    </script>
@endsection
