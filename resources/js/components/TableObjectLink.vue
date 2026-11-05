<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import TableLink from '@/components/TableLink.vue';

/**
 * A name that refers to another back office object (store-table-object-
 * links.md): a TableLink to the object's page when the signed-in user may open
 * it, otherwise the same text, unlinked. `allowed` is the permission check the
 * target's own list uses for its edit/show link, such as
 * `access.can('edit_customer')`.
 */
defineProps<{
    href: string | UrlMethodPair;
    allowed: boolean;
}>();
</script>

<template>
    <TableLink v-if="allowed" :href="href"><slot /></TableLink>
    <slot v-else />
</template>
