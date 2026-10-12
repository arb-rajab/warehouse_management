<script>
    function tripMapCoordinates(latitude, longitude) {
        if ((typeof latitude !== 'number' && typeof latitude !== 'string') ||
            (typeof longitude !== 'number' && typeof longitude !== 'string') ||
            String(latitude).trim() === '' || String(longitude).trim() === '') {
            return null;
        }
        const lat = Number(latitude);
        const lng = Number(longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return null;
        }
        return [lat, lng];
    }

    function tripMapPopup(rows, orderId = null) {
        const popup = document.createElement('div');
        rows.forEach(function(row) {
            const line = document.createElement('div');
            if (row[0] !== null) {
                const label = document.createElement('strong');
                label.textContent = row[0] + ': ';
                line.append(label);
            }
            const value = document.createElement('span');
            value.textContent = row[1] ?? '';
            line.append(value);
            popup.append(line);
        });
        if (orderId !== null && @json(auth('store')->user()?->can('edit_trip') ?? false)) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-danger btn-sm mark-delivered';
            button.dataset.orderId = String(orderId);
            button.textContent = @json(translate('Mark as Delivered'));
            popup.append(button);
        }
        return popup;
    }
</script>
