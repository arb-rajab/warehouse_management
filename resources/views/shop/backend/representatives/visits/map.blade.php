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
    .custom-marker.red { background-color: #ff0000; }
    .custom-marker.blue { background-color: #007bff; }
</style>
<script>
    (() => {
        const element = document.getElementById('map');
        if (!element) {
            return;
        }

        const map = L.map(element).setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const markers = @json($mapMarkers);
        const points = [];
        markers.forEach((marker) => {
            const popup = document.createElement('span');
            popup.textContent = marker.label;
            const icon = L.divIcon({
                className: marker.has_order ? 'custom-marker blue' : 'custom-marker red',
                html: String(marker.rank),
            });
            L.marker([marker.lat, marker.lng], { icon }).addTo(map).bindPopup(popup);
            points.push([marker.lat, marker.lng]);
        });

        if (points.length > 1) {
            L.polyline(points).addTo(map);
            map.fitBounds(points, { padding: [24, 24], maxZoom: 14 });
        } else if (points.length === 1) {
            map.setView(points[0], 14);
        }
    })();
</script>
