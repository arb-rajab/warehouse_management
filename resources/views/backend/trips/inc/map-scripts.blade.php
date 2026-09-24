<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the map
        var map = L.map('map').setView([0, 0], 2); // Centered at (0, 0) with zoom level 2

        // Add the base OSM tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Array to store markers
        var markers = [];

        // Function to fetch coordinates from API
        function fetchCoordinates(id, type = null) {
            fetch(`/api/get-user-coordinates/${id}?type=${type}`) // Your route URL here
                .then(response => response.json())
                .then(data => {
                    if (data && data.latitude && data.longitude) {
                        // Add a marker to the map
                        addMarker(data.latitude, data.longitude, type || "User",
                            `${type || "User"}'s checkpoint`);
                    } else {
                        console.error("Invalid data:", data);
                    }
                })
                .catch(error => console.error("Error fetching coordinates:", error));
        }

        // Function to add a marker to the map
        function addMarker(lat, lng, rank, label) {
            var marker = L.marker([lat, lng]).addTo(map)
                .bindPopup("Rank: " + rank + "<br>Notes: " + label);

            // Store the marker
            markers.push(marker);

            // Adjust map view to fit all markers
            var latLngBounds = new L.LatLngBounds(markers.map(function(marker) {
                return marker.getLatLng();
            }));
            map.fitBounds(latLngBounds, {
                padding: [50, 50]
            });
        }

        // Function to clear all markers from the map
        function clearMarkers() {
            markers.forEach(marker => map.removeLayer(marker));
            markers = []; // Clear the markers array
        }

        // Check initial values of select elements on page load
        var userSelect = document.querySelector('.user-id-input');
        if (userSelect && userSelect.value) {
            fetchCoordinates(userSelect.value);
        }

        var orderSelect = document.querySelector('.order-id-input');
        if (orderSelect && orderSelect.value) {
            fetchCoordinates(orderSelect.value, 'order');
        }

        // Add event listeners for changes on select elements
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('user-id-input')) {
                var userId = event.target.value;
                if (userId) {
                    fetchCoordinates(userId);
                } else {
                    console.error('User ID not selected.');
                }
            }

            if (event.target.classList.contains('order-id-input')) {
                var orderId = event.target.value;
                if (orderId) {
                    fetchCoordinates(orderId, 'order');
                } else {
                    console.error('Order ID not selected.');
                }
            }
        });

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-checkpoint')) {
                clearMarkers();

                // Re-fetch data for currently selected values
                document.querySelectorAll('.user-id-input, .order-id-input').forEach(select => {
                    if (select.value) {
                        const type = select.classList.contains('order-id-input') ? 'order' : null;
                        fetchCoordinates(select.value, type);
                    }
                });
            }
        });
    });
</script>
