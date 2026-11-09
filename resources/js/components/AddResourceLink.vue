<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

withDefaults(
    defineProps<{
        href: string | UrlMethodPair;
        label: string;
        inertia?: boolean;
    }>(),
    { inertia: true },
);
</script>

<template>
    <component
        :is="inertia ? Link : 'a'"
        :href="inertia || typeof href === 'string' ? href : href.url"
        class="inline-flex items-center gap-1.5 rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-on-brand shadow-btn hover:bg-brand-hover"
    >
        <Plus class="h-4 w-4" />
        {{ label }}
    </component>
</template>
