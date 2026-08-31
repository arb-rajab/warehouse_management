<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';

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
    primary: primaryPillVariantClass,
    danger: 'bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600',
};
</script>

<template>
    <Link :href="href" :class="[pillLinkClass, variantClasses[variant]]">
        <slot />
    </Link>
</template>
