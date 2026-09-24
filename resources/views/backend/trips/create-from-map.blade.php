@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Create Trip')}}</h5>
</div>

<div class="col-lg mx-auto">
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Trip') }}</h5>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div id="map" style="height: 30rem; width: auto; border: 0; margin: auto;"></div>
                </div>
            </div>

            <div class="container">
                {{-- <h2>Select a Truck</h2> --}}
                <div class="mt-2 row">

                    @forelse($available_trucks as $truck)
                    <div class="col-md-3">
                        <div class="card truck-card mx-1" style="width: 12rem;">
                            <img src="{{ get_images_path($truck->photos)[0] ?? static_asset("assets/img/default_truck.png") }}" class="card-img-top" alt="{{ $truck->name }}" width="150" height="150">
                            <div class="card-body">
                                <h5 class="card-title">{{ $truck->name }}</h5>
                                <p class="card-body">{{ __('Max Pallets Number') . ':' . $truck->max_pallets_number }}</p>
                                {{-- <p class="card-text">{{ $truck->description }}</p> --}}
                                <div class="row justify-content-center align-items-center mt-3">
                                    <div class="col-auto">
                                        <button class="btn btn-soft-success btn-icon btn-circle btn-sm select-truck"
                                            data-truck-id="{{ $truck->id }}"
                                            data-trip-id="{{ $truck->lastTrip()->id ?? null }}"
                                            title="{{ translate('Select Truck') }}">
                                            <i class="las la-check"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            <div class="alert alert-warning" role="alert">
                                <h2><i class="fas fa-exclamation-circle"></i> {{ translate('No Trucks available') }}</h2>
                            </div>
                        </div>
                    </div>
                    @endforelse
                </div>

                {{-- <div class="mt-2 row">
                    <h6>{{ __("Unavailable trucks") }}:</h6>
            </div> --}}
            <div class="mt-2 row">
                @forelse ($unavailable_trucks as $truck)
                <div class="col-md-3 mb-4">
                    <div class="card truck-card h-100 shadow-sm" style="width: 12rem;">
                        <img
                            src="{{ get_images_path($truck->photos)[0] ?? static_asset('assets/img/default_truck.png') }}"
                            class="card-img-top img-fluid aspect-ratio-1x1"
                            alt="{{ __('Truck image of') }} {{ $truck->name }}"
                            loading="lazy">
                        <div class="card-body d-flex flex-column">
                            <a href="{{ route('trips.show', $truck->lastTrip()->id) }}">
                                <h2 class="card-title h5 mb-3">
                                    {{ $truck->name }}
                                </h2>
                            </a>
                            @if($truck->lastTrip() && $truck->lastTrip()->checkpoints->isNotEmpty())
                            <h6 class="mb-3">
                                {{ $truck->lastTrip()->driver?->name }}
                            </h6>
                            <ul class="list-unstyled mb-0">
                               @foreach($truck->openTrips() as $trip)
                                @foreach($trip->checkpoints as $checkpoint)
                                    @php
                                        $customer = $checkpoint->getCustomerForCheckpoint();
                                    @endphp

                                    <li class="mb-2">
                                        <div class="text-secondary small">{{ __('Next Stop') }}</div>
                                        <strong class="d-block">
                                            @if($customer)
                                                <a href="{{ route('customers.edit', $customer->id) }}">
                                                    {{ $customer->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">عميل غير معروف</span>
                                            @endif
                                        </strong>
                                    </li>
                                @endforeach

                                <div class="text-secondary small fs-2">
                                    {{ __('Checkpoints Count') }} : {{ $trip->checkpoints->count() }}
                                </div>
                            @endforeach


                            </ul>
                            @else
                            <div class="text-muted small">
                                {{ __('No current trip information') }}
                            </div>
                            @endif
                            <div class="row justify-content-center align-items-center mt-3">
                                <div class="col-auto">
                                    <button class="btn btn-soft-success btn-icon btn-circle btn-sm select-truck"
                                        data-truck-id="{{ $truck->id }}"
                                        data-trip-id="{{ $truck->lastTrip()->id ?? null }}"
                                        title="{{ translate('Select Truck') }}">
                                        <i class="las la-check"></i>
                                    </button>
                                </div>
                                <div class="col-auto">
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                                        data-href="{{ route('trips.destroy', ['id' => $truck->lastTrip()->id]) }}"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="row">
                    <div class="col-lg-12 text-center">
                        <div class="alert alert-success" role="alert">
                            <h2><i class="fas fa-exclamation-circle"></i> {{ translate('All Trucks are available') }}</h2>
                        </div>
                    </div>
                </div>
                @endforelse
            </div>

            {{-- <button type="button" id="add-markers-to-truck" class="btn btn-success">{{ __("Add Selected Markers to Truck") }}</button> --}}
        </div>
    </div>
</div>
</div>

@include('modals.delete_modal')
@endsection

@section('style')
@include('backend.trips.inc.styles')
@endsection

@section('script')
@include('backend.trips.inc.scripts')
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script>
    // Marker data passed from PHP
    var markersData = @json($long_lat_array);

    var selectedMarkers = []; // Array to store selected markers' data

    var selectedIcon = L.icon({
        iconUrl: '{{ static_asset("assets/img/selected-marker.png") }}',
        iconSize: [30, 45],
        iconAnchor: [15, 45],
        popupAnchor: [1, -34]
    });

    // Check if markersData is empty or null
    if (!markersData || markersData.length === 0) {
        map = L.map('map').setView([52.3676, 4.9041], 7); // Default to Netherlands
    } else {
        map = L.map('map').setView([parseFloat(markersData[0].latitude), parseFloat(markersData[0].longitude)], 8);
    }

    // Add the base OSM tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Initialize LatLngBounds
    var latLngBounds = new L.LatLngBounds();

    // Function to handle marker click
    function handleMarkerClick(e) {
        var marker = e.target;
        var markerData = marker.options.data; // Access the custom data here

        // Toggle selection (Add to array if not already selected, otherwise remove)
        if (selectedMarkers.includes(markerData)) {
            // Deselect marker
            selectedMarkers = selectedMarkers.filter(m => m !== markerData);
            marker.setIcon(L.Icon.Default.prototype);
            return;
        } else {
            // Select marker
            selectedMarkers.push(markerData);
            marker.setIcon(selectedIcon);
        }

    }
    var markerPositions = {};

    function adjustMarkerPosition(lat, lng) {
        var key = lat.toFixed(6) + ',' + lng.toFixed(6); // Ensure precision for tracking

        if (markerPositions[key] !== undefined) {
            markerPositions[key]++; // Increase counter for this position
        } else {
            markerPositions[key] = 0;
        }

        var offset = markerPositions[key] * 0.0011; // Small offset to prevent overlap
        console.log(offset);

        return [lat, lng + offset]; // Return adjusted position
    }


    // Add markers to the map
    markersData.forEach(function(markerData) {
        var lat = parseFloat(markerData.latitude);
        var lng = parseFloat(markerData.longitude);

        // Validate the coordinates
        if (isNaN(lat) || isNaN(lng)) {
            console.warn("Invalid coordinates for marker:", markerData);
            return;
        }

        // Adjust position if needed
        var adjustedPosition = adjustMarkerPosition(lat, lng);

        // Create a Leaflet marker
        var marker = L.marker(adjustedPosition).addTo(map)
            .bindPopup(
                "<b>" + markerData.label + "</b><br>" + // customer name
                "<b>Order Code:</b> " + markerData.order_code + "<br>" +
                "<b>Customer ID:</b> " + markerData.user_id + "<br>" +
                "<b>Post Code:</b> " + markerData.postal_code + "<br>" +
                "<b>Phone:</b> " + markerData.phone + "<br>" +
                "<b>Shipping Address:</b> " + markerData.shipping_address + "<br>" +
                "<b>Otajer ID:</b> " + markerData.otajer_id + "<br>" +
                '<button class="btn btn-danger btn-sm mark-delivered" data-order-id="' + markerData.order_id + '">Mark as Delivered</button>'
            );

        // Store custom data in the marker options
        marker.options.data = markerData;

        // Add click event listener to the marker
        marker.on('click', handleMarkerClick);

        // Extend the LatLngBounds
        latLngBounds.extend([lat, lng]);
    });

    // Fit the map to the LatLngBounds with padding
    if (latLngBounds.isValid()) {
        map.fitBounds(latLngBounds, {
            padding: [50, 50]
        });
    } else {
        console.warn("LatLngBounds is not valid.");
    }

    var selectedTruckId = null;

    // Listen for the truck selection
    document.querySelectorAll('.select-truck').forEach(function(button) {
        button.addEventListener('click', function() {
            selectedTruckId = this.getAttribute('data-truck-id');

            addMarkersToTruck(this);
        });
    });

    function addMarkersToTruck(e) {
        if (!selectedTruckId || selectedMarkers.length === 0) {
            alert('Please select a truck and markers.');
            return;
        }

        // Disable the button after clicking
        e.disabled = true;
        e.innerHTML = '<i class="las la-spinner la-spin"></i>'; // Show processing spinner

        var tripId = e.getAttribute('data-trip-id'); // Get the trip ID from the button

        var truckMarkersData = {
            truck_id: selectedTruckId,
            trip_id: tripId, // Include the trip ID
            markers: selectedMarkers
        };


        // Send the data to the server via AJAX
        fetch('{{ route("trips.store-from-map") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') // Laravel CSRF Token
                },
                body: JSON.stringify(truckMarkersData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    // Button to add selected markers to truck
    // document.getElementById('add-markers-to-truck').addEventListener('click', addMarkersToTruck);


    // Event listener for the 'Mark as Delivered' button
    document.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('mark-delivered')) {
            var orderId = event.target.getAttribute('data-order-id');

            fetch('{{ route("trips.orders.update_order_status_to_delivered") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') // Laravel CSRF Token
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Failed to mark order as delivered.");
                    }
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                });
        }
    });
</script>
@endsection