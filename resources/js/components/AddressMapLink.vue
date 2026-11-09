<script setup lang="ts">
import { computed } from 'vue';
import { googleMapsUrl } from '@/lib/address';
import { t } from '@/lib/i18n';

const props = defineProps<{
    address?: string | null;
    placeholder?: string;
}>();

const trimmed = computed(() => props.address?.trim() ?? '');
</script>

<template>
    <a
        v-if="trimmed !== ''"
        :href="googleMapsUrl(trimmed)"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="t('common.openInMaps', { address: trimmed })"
        class="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-focus focus-visible:outline-none"
        >{{ address }}</a
    >
    <span v-else-if="placeholder">{{ placeholder }}</span>
</template>
