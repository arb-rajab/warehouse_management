@extends('backend.layouts.app')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Visit Details') }}</h1>
        </div>
        <div class="card-body">
            <div class="row gutters-5">
                <div class="col text-md-left text-center">
                </div>
                @php
                    $delivery_status = $visit->order?->delivery_status;
                @endphp

            </div>

            <div class="row gutters-5">
                <div class="col-md-4 ml-auto">
                    <table>
                        <tbody>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Customer Name') }}</td>
                                <td class="pl-4 text-left"> {{ $visit->customer->name }}</td>
                            </tr>

                            <tr>
                                <td class="text-main text-bold">{{ translate('Company Name') }} </td>
                                <td class="pl-4 text-left">{{ $visit->customer->company_name }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Email') }} </td>
                                <td class="pl-4 text-left">{{ $visit->customer->email }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Phone') }} </td>
                                <td class="pl-4 text-left">{{ $visit->customer->phone }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Customer Serial') }} </td>
                                <td class="pl-4 text-left">{{ $visit->customer->member_serial }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Account ID') }} </td>
                                <td class="pl-4 text-left">{{ $visit->customer->AccSysID }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Visit Date') }} </td>
                                <td class="pl-4 text-left">{{ $visit->visit_date }}</td>
                            </tr>

                        </tbody>
                    </table>
                </div>
                @if($visit->order)
                    <div class="col-md-4 ml-auto">
                        <table>
                            <tbody>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order #') }}</td>
                                    <td class="text-info text-bold pl-4 text-left"> {{ $visit->order->code }}</td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                                    <td class="pl-4 text-left">
                                        @if ($delivery_status == 'delivered')
                                            <span class="badge badge-inline badge-success">
                                                {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                            </span>
                                        @else
                                            <span class="badge badge-inline badge-info">
                                                {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Order Date') }} </td>
                                    <td class="pl-4 text-left">{{ date('d-m-Y h:i A', $visit->order->date) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">
                                        {{ translate('Total amount') }}
                                    </td>
                                    <td class="pl-4 text-left">
                                        {{ single_price($visit->order->grand_total) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                                    <td class="pl-4 text-left">
                                        {{ translate(ucfirst(str_replace('_', ' ', $visit->order->payment_type))) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Customer Notes') }}</td>
                                    <td class="pl-4 text-left">{{ $visit->order->additional_info }}</td>
                                </tr>

                                @if($visit->order->by_rep && $visit->order->rep != null)
                                <tr>
                                    <td class="text-main text-bold">{{ translate('Customer Otajer ID') }}</td>
                                    <td class="pl-4 text-left">{{ $visit->order->rep->AccSysID }}</td>
                                </tr>
                                @endif


                                <tr>
                                    <td class="text-main text-bold">{{ translate('Delivery Date') }}</td>
                                    @isset($visit->order->delivery_date)
                                    <td class="pl-4 text-left"> {{ date('d-m-Y', strtotime($visit->order->delivery_date)) }} </td>
                                    @endisset
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                <div class="col-md-4 ml-auto">
                    <h1 class="h2 fs-16 mb-0">{{ translate("There is no specific order for this visit.") }}</h1>
                </div>
                @endif
            </div>
            <hr class="new-section-sm bord-no">
            <div class="row">
                <div class="col-lg-12">
                    <div id="map" style="height: 30rem; width: auto; border:0; margin: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize the map centered at (0, 0) with zoom level 2
        var map = L.map('map');

        // Add the base OSM tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy;'
        }).addTo(map);

        // Build markers data array
        var markers = [
            { lat: {{ $visit->map_lat }}, lng: {{ $visit->map_lng }}, label: "{{ $visit->customer->name }}"}
        ];

        // Create a LatLngBounds object to calculate bounds for the map
        var latLngBounds = new L.LatLngBounds();
        // Create an array for the polyline points
        var polylinePoints = [];

        // Add markers to the map. Also, store their lat/lng pairs for the polyline and bounds.
        markers.forEach(function(marker) {
            // Create a new marker and add it to the map
            var addedMarker = L.marker([marker.lat, marker.lng]).addTo(map);
            // Add popup with label to the marker
            addedMarker.bindPopup(marker.label);
            // Extend our LatLngBounds object with each marker
            latLngBounds.extend([marker.lat, marker.lng]);
            // Push the marker to our array of polyline points
            polylinePoints.push([marker.lat, marker.lng]);
        });

        // Draw the polyline with our points
        var polyline = L.polyline(polylinePoints).addTo(map);

        // Fit the map to the LatLngBounds with some padding
        map.fitBounds(latLngBounds);
        // Decrease zoom level by 4 to show all markers
        map.setZoom(map.getZoom() - 2);

    </script>
@endsection
