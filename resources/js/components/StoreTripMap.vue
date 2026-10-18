<script setup lang="ts">
import { icon, latLngBounds, marker } from 'leaflet';
import type { LeafletKeyboardEvent, Map as LeafletMap, Marker } from 'leaflet';
import markerIconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { watch } from 'vue';
import StoreLeafletMap from '@/components/StoreLeafletMap.vue';
import { t } from '@/lib/i18n';

/** A point on the map: an order, labelled for its marker. */
export interface StoreTripMapPoint {
    id: number;
    latitude: number;
    longitude: number;
    label: string;
}

/**
 * The trip map picker's Leaflet map (store-backoffice-migration.md §30): one
 * marker per point on OpenStreetMap tiles, fitted to the points. Clicking a
 * marker (or pressing Enter or Space on it) emits `toggle` with its point's id; a
 * selected point's marker takes the highlighted icon and `aria-pressed`.
 * Leaflet and its stylesheet come from the npm package through Vite.
 */
const props = defineProps<{
    points: StoreTripMapPoint[];
    selected: number[];
}>();
const emit = defineEmits<{ toggle: [id: number] }>();

const iconOptions = {
    iconUrl: markerIconUrl,
    iconRetinaUrl: markerIconRetinaUrl,
    shadowUrl: markerShadowUrl,
    iconSize: [25, 41] as [number, number],
    iconAnchor: [12, 41] as [number, number],
    popupAnchor: [1, -34] as [number, number],
    shadowSize: [41, 41] as [number, number],
};
const defaultIcon = icon(iconOptions);
const selectedIcon = icon({
    ...iconOptions,
    className: 'hue-rotate-150 saturate-200',
});
let map: LeafletMap | null = null;
let markers = new Map<number, Marker>();

/**
 * Markers at the same spot are spread east by a small step each, as the Blade
 * picker did, so every order stays clickable.
 */
function spread(points: StoreTripMapPoint[]): Map<number, [number, number]> {
    const seen = new Map<string, number>();
    const positions = new Map<number, [number, number]>();

    for (const point of points) {
        const key = `${point.latitude.toFixed(6)},${point.longitude.toFixed(6)}`;
        const count = seen.get(key) ?? 0;
        seen.set(key, count + 1);
        positions.set(point.id, [
            point.latitude,
            point.longitude + count * 0.0011,
        ]);
    }

    return positions;
}

function markSelected(id: number, item: Marker): void {
    const isSelected = props.selected.includes(id);
    item.setIcon(isSelected ? selectedIcon : defaultIcon);
    item.getElement()?.setAttribute('aria-pressed', String(isSelected));
}

function drawMarkers(): void {
    if (!map) {
        return;
    }

    markers.forEach((item) => item.remove());
    markers = new Map();

    // The view comes first: Leaflet only adds layers (and creates their
    // elements) once the map has one.
    if (props.points.length > 0) {
        map.fitBounds(
            latLngBounds(
                props.points.map((point) => [point.latitude, point.longitude]),
            ),
            { padding: [50, 50] },
        );
    } else {
        map.setView([0, 0], 2);
    }

    const positions = spread(props.points);

    for (const point of props.points) {
        const item = marker(positions.get(point.id)!, {
            icon: defaultIcon,
            title: point.label,
            alt: point.label,
            keyboard: true,
        });
        item.on('click', () => emit('toggle', point.id));
        // A marker is a focusable role="button"; Leaflet itself maps Enter
        // only to opening a popup, so Enter and Space toggle here.
        item.on('keydown', (event) => {
            const key = (event as LeafletKeyboardEvent).originalEvent.key;

            if (key === 'Enter' || key === ' ') {
                (event as LeafletKeyboardEvent).originalEvent.preventDefault();
                emit('toggle', point.id);
            }
        });
        item.addTo(map);
        markers.set(point.id, item);
        markSelected(point.id, item);
    }
}

function initialize(readyMap: LeafletMap): void {
    map = readyMap;
    drawMarkers();
}
watch(() => props.points, drawMarkers);
watch(
    () => props.selected,
    () => markers.forEach((item, id) => markSelected(id, item)),
    { deep: true },
);
</script>

<template>
    <StoreLeafletMap
        :label="t('store.trips.map.mapLabel')"
        @ready="initialize"
        data-testid="store-trip-map"
    />
</template>
