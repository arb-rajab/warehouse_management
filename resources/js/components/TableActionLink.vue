<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        href: string | UrlMethodPair;
        variant?: 'primary' | 'danger';
    }>(),
    {
        variant: 'primary',
    },
);

const variantClasses: Record<'primary' | 'danger', string> = {
    primary:
        'bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600',
    danger: 'bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600',
};
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-1 rounded-md px-3 py-1 text-xs font-medium text-white"
        :class="variantClasses[variant]"
    >
        <slot />
    </Link>
</template>
