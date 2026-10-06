<script setup lang="ts">
import { t } from '@/lib/i18n';

defineProps<{
    active: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-xs font-medium"
        :class="
            active
                ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                : 'bg-gray-200 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300'
        "
        data-testid="product-active-badge"
        >{{
            active ? t('products.status.active') : t('products.status.inactive')
        }}</span
    >
</template>
