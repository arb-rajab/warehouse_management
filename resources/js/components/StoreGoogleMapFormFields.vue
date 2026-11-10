<script setup lang="ts">
import { divIcon, marker } from 'leaflet';
import type { LeafletMouseEvent, Map as LeafletMap, Marker } from 'leaflet';
import { computed, ref, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreLeafletMap from '@/components/StoreLeafletMap.vue';
import { t } from '@/lib/i18n';

export interface StoreGoogleMapSettings {
    google_map: boolean;
    google_map_latitude: string;
    google_map_longtitude: string;
    /** Whether a key is saved on this page; the key itself is never sent. */
    google_map_api_key_set: boolean;
    /** Bullets plus the last four characters of the saved key, or ''. */
    google_map_api_key_mask: string;
    /** Whether MAP_API_KEY supplies a key. */
    google_map_api_key_from_env: boolean;
}
const props = defineProps<{
    settings: StoreGoogleMapSettings;
    errors: Record<string, string>;
}>();
const enabled = ref(props.settings.google_map);
const latitude = ref(props.settings.google_map_latitude);
const longitude = ref(props.settings.google_map_longtitude);
const keyAvailable =
    props.settings.google_map_api_key_set ||
    props.settings.google_map_api_key_from_env;
// Same condition as UpdateGoogleMapRequest: the map is on and no key (saved or
// from MAP_API_KEY) exists; otherwise a blank field keeps the saved key.
const keyRequired = computed(() => enabled.value && !keyAvailable);
const keyStatus = computed(() =>
    props.settings.google_map_api_key_set
        ? 'apiKeyStatusSaved'
        : props.settings.google_map_api_key_from_env
          ? 'apiKeyStatusEnv'
          : 'apiKeyStatusNone',
);
let map: LeafletMap | null = null;
let pin: Marker | null = null;

function coordinates(): [number, number] | null {
    const lat = Number(latitude.value);
    const lng = Number(longitude.value);

    return latitude.value.trim() !== '' &&
        longitude.value.trim() !== '' &&
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat >= -90 &&
        lat <= 90 &&
        lng >= -180 &&
        lng <= 180
        ? [lat, lng]
        : null;
}
function movePin(): void {
    if (!map) {
        return;
    }

    const point = coordinates();

    if (!point) {
        pin?.remove();
        pin = null;

        return;
    }

    if (pin) {
        pin.setLatLng(point);
    } else {
        pin = marker(point, {
            draggable: true,
            title: t('store.googleMap.location'),
            alt: t('store.googleMap.location'),
            icon: divIcon({
                className: 'store-default-location-marker',
                html: '<span aria-hidden="true">●</span>',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
            }),
        }).addTo(map);
        pin.on('dragend', () => {
            const point = pin!.getLatLng();
            pick(point.lat, point.lng);
        });
    }

    map.panTo(point);
}
function pick(lat: number, lng: number): void {
    latitude.value = String(Math.max(-90, Math.min(90, lat)));
    longitude.value = String(((((lng + 180) % 360) + 360) % 360) - 180);
}
function initialize(readyMap: LeafletMap): void {
    map = readyMap;
    pin = null;
    map.setView(coordinates() ?? [0, 0], coordinates() ? 13 : 2);
    map.on('click', (event: LeafletMouseEvent) =>
        pick(event.latlng.lat, event.latlng.lng),
    );
    movePin();
}
function toggle(event: Event): void {
    enabled.value = (event.target as HTMLSelectElement).value === '1';

    if (!enabled.value) {
        map = null;
        pin = null;
    }
}
watch([latitude, longitude], movePin);
</script>

<template>
    <div class="space-y-4">
        <StoreBooleanField
            name="google_map"
            :value="enabled"
            :label="t('store.googleMap.enabled')"
            :error="errors.google_map"
            :required="!enabled || (latitude === '' && longitude === '')"
            @change="toggle"
        />
        <p class="text-sm text-muted">
            {{ t('store.googleMap.requirementsHint') }}
        </p>
        <div>
            <FormField
                id="google_map_api_key"
                :label="t('store.googleMap.apiKey')"
                :placeholder="
                    settings.google_map_api_key_mask ||
                    t('store.googleMap.apiKeyPlaceholder')
                "
                :required="keyRequired"
                :error="errors.google_map_api_key"
                autocomplete="off"
                spellcheck="false"
                maxlength="255"
                dir="ltr"
                aria-describedby="google_map_api_key_help"
            />
            <div
                id="google_map_api_key_help"
                class="mt-1 space-y-1 text-sm text-muted"
            >
                <p>{{ t(`store.googleMap.${keyStatus}`) }}</p>
                <p>{{ t('store.googleMap.apiKeyHelp') }}</p>
                <p v-if="keyRequired">
                    {{ t('store.googleMap.apiKeyRequiredHint') }}
                </p>
            </div>
        </div>
        <template v-if="enabled">
            <h2 class="text-lg font-semibold">
                {{ t('store.googleMap.location') }}
            </h2>
            <p class="text-sm text-muted">
                {{ t('store.googleMap.hint') }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="google_map_latitude"
                    :value.prop="latitude"
                    :label="t('store.googleMap.latitude')"
                    type="number"
                    min="-90"
                    max="90"
                    step="any"
                    :required="longitude !== ''"
                    :error="errors.google_map_latitude"
                    @input="
                        latitude = ($event.target as HTMLInputElement).value
                    "
                />
                <FormField
                    id="google_map_longtitude"
                    :value.prop="longitude"
                    :label="t('store.googleMap.longitude')"
                    type="number"
                    min="-180"
                    max="180"
                    step="any"
                    :required="latitude !== ''"
                    :error="errors.google_map_longtitude"
                    @input="
                        longitude = ($event.target as HTMLInputElement).value
                    "
                />
            </div>
            <StoreLeafletMap
                :label="t('store.googleMap.mapLabel')"
                @ready="initialize"
            />
        </template>
    </div>
</template>

<style scoped>
:deep(.store-default-location-marker) {
    border: 2px solid var(--surface);
    border-radius: 50%;
    background: var(--info-color);
    color: var(--page);
    text-align: center;
    line-height: 26px;
    box-shadow: var(--shadow-card);
}
</style>
