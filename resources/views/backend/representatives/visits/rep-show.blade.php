@extends('backend.layouts.app')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <div class="card">
        <form class="" id="sort_visits" action="" method="GET">
            <div class="card-header">
                <h1 class="h2 fs-16 mb-0">{{ translate('Visit Details') }}</h1>
                <h1 class="h2 fs-16 mb-0">{{ $rep->name . "'s " . translate('Visits')}}</h1>

                <div class="col-lg-2">
                    <div class="form-group mb-0">
                        <input type="date" class="form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" autocomplete="off">
                        {{-- <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off"> --}}
                    </div>
                </div>

                <div class="col-auto">
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row gutters-5">
                    <div class="col text-md-left text-center">
                    </div>
                </div>
                {{-- <hr class="new-section-sm bord-no"> --}}
                @if($visits->count() > 0)
                    <div class="row">
                        <div class="col-lg-12">
                            <div id="map" style="height: 30rem; width: auto; border:0; margin: auto;"></div>
                        </div>
                    </div>
                @else
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            <div class="alert alert-warning" role="alert">
                                <h2><i class="fas fa-exclamation-circle"></i> {{ translate('No visits found on this date') }}</h2>
                                <p>{{ translate('Please try selecting a different date.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </div>
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
    </style>
    <script>
        // Initialize the map
        var map = L.map('map'); // Centered at (0, 0) with zoom level 2

        // Add the base OSM tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy;'
        }).addTo(map);

        var markers = [
            @foreach ($visits as $visit)
                @if ($visit->map_lat && $visit->map_lng)
                    { lat: {{ $visit->map_lat }}, lng: {{ $visit->map_lng }}, id: {{ $visit->id }}, rank: {{ $visit->rank + 1 }}, order_id: {{ $visit->order_id ? 'true' : 'null' }}, label: "{{ $visit->customer->name }}" },
                @endif
            @endforeach
        ];

        markers.forEach(function(marker) {
            var markerClass = marker.order_id === null ? 'custom-marker red' : 'custom-marker blue';
            var customIcon = L.divIcon({
                className: markerClass,
            });

            L.marker([marker.lat, marker.lng], { icon: customIcon }).addTo(map);
        });

        // Create a LatLngBounds object
        var latLngBounds = new L.LatLngBounds();

        // Create an array for the polyline points
        var polylinePoints = [];

        // Add markers to the map and store their lat/lng pairs for the polyline
        markers.forEach(function(marker) {
            L.marker([marker.lat, marker.lng]).addTo(map)
                .bindPopup(marker.label); // Add popup with label
            latLngBounds.extend([marker.lat, marker.lng]);
            polylinePoints.push([marker.lat, marker.lng]);
        });

        // Draw the polyline
        var polyline = L.polyline(polylinePoints).addTo(map);

        // Fit the map to the LatLngBounds with some padding
        map.fitBounds(latLngBounds);
        map.setZoom(map.getZoom() - 1); // Decrease zoom level by 1

    </script>
@endsection
