<script setup lang="ts">
import SidebarNavLink from '@/components/SidebarNavLink.vue';
import { t } from '@/lib/i18n';
import type { StoreMenuLink } from '@/lib/storeAdminMenu';

/**
 * One sidebar link of StoreAdminLayout, drawn by the shared SidebarNavLink.
 * A converted page is an Inertia visit; every other target is still a Blade
 * page and loads in full (.ai/rules/store-backoffice-migration.md §2.6).
 */
defineProps<{
    item: StoreMenuLink;
    active: boolean;
    count?: number;
    collapsed?: boolean;
}>();
</script>

<template>
    <SidebarNavLink
        :href="item.href"
        :inertia="!!item.inertia"
        :label="t(item.labelKey)"
        :icon="item.icon"
        :active="active"
        :count="count"
        :collapsed="collapsed"
    />
</template>
