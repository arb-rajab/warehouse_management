<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

defineProps<{
    href: string | UrlMethodPair;
    label: string;
}>();
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
    >
        <Plus class="h-4 w-4" />
        {{ label }}
    </Link>
</template>
