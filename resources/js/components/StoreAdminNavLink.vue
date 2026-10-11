<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';
import type { StoreMenuLink } from '@/lib/storeAdminMenu';

/**
 * One sidebar link of StoreAdminLayout. A converted page is an Inertia
 * visit; every other target is still a Blade page and loads in full, so
 * Inertia never receives its HTML (.ai/rules/store-backoffice-migration.md §2.6).
 */
defineProps<{
    item: StoreMenuLink;
    active: boolean;
    count?: number;
}>();
</script>

<template>
    <component
        :is="item.inertia ? Link : 'a'"
        :href="item.inertia ? item.href : item.href.url"
        :aria-current="active ? 'page' : undefined"
        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors"
        :class="
            active
                ? 'bg-gray-100 font-medium text-gray-900 dark:bg-neutral-800 dark:text-white'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
        "
    >
        <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0" />
        {{ t(item.labelKey) }}
        <span
            v-if="count"
            class="ms-auto rounded-full bg-blue-100 px-2 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-100"
            >{{ count }}</span
        >
    </component>
</template>
