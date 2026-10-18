<script setup lang="ts">
import { divIcon, marker, polyline } from 'leaflet';
import type { Layer, Map as LeafletMap } from 'leaflet';
import { watch } from 'vue';
import StoreLeafletMap from '@/components/StoreLeafletMap.vue';
import { t } from '@/lib/i18n';
import type { RepresentativeVisitMarker } from '@/types/storeRepresentatives';

const props = defineProps<{ markers: RepresentativeVisitMarker[] }>();
let map: LeafletMap | null = null;
let layers: Layer[] = [];

function draw(): void {
    if (!map) {
        return;
    }

    layers.forEach((layer) => layer.remove());
    layers = [];
    const points: [number, number][] = props.markers.map((point) => [
        point.lat,
        point.lng,
    ]);

    // Set the view before adding layers, so Leaflet creates their elements.
    if (points.length > 1) {
        map.fitBounds(points, { padding: [24, 24], maxZoom: 14 });
    } else {
        map.setView(points[0] ?? [0, 0], points.length ? 14 : 2);
    }

    for (const point of props.markers) {
        const popup = document.createElement('span');
        popup.textContent = point.label;
        layers.push(
            marker([point.lat, point.lng], {
                icon: divIcon({
                    className: `store-visit-marker ${point.has_order ? 'blue' : 'red'}`,
                    html: String(point.rank),
                    iconSize: [30, 30],
                    iconAnchor: [15, 15],
                }),
                title: point.label,
                alt: point.label,
                keyboard: true,
            })
                .addTo(map)
                .bindPopup(popup),
        );
    }

    if (points.length > 1) {
        layers.push(polyline(points).addTo(map));
    }
}
function initialize(readyMap: LeafletMap): void {
    map = readyMap;
    draw();
}
watch(() => props.markers, draw);
</script>

<template>
    <StoreLeafletMap
        :label="t('store.representatives.visits.mapLabel')"
        @ready="initialize"
        data-testid="store-visit-map"
    />
</template>

<style scoped>
:deep(.store-visit-marker) {
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    color: #fff;
    font-weight: bold;
}
:deep(.store-visit-marker.red) {
    background-color: #dc2626;
}
:deep(.store-visit-marker.blue) {
    background-color: #1d4ed8;
}
</style>
