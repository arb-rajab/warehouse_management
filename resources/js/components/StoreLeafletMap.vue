<script setup lang="ts">
import { map as leafletMap, tileLayer } from 'leaflet';
import type { Map as LeafletMap } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{ label: string }>();
const emit = defineEmits<{ ready: [map: LeafletMap] }>();
const container = ref<HTMLElement | null>(null);
let map: LeafletMap | null = null;

onMounted(() => {
    map = leafletMap(container.value!);
    tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    emit('ready', map);
});
onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div
        ref="container"
        dir="ltr"
        role="region"
        :aria-label="label"
        class="isolate h-120 w-full rounded-md border border-gray-200 dark:border-neutral-700"
    />
</template>
